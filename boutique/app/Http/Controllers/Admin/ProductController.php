<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Store;
use App\Services\ImageProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * S14 / S15 : produits, photos et variantes (tailles, couleurs).
 *
 * Sans variante : un prix et un stock, enregistrés sur une variante "Standard".
 * Avec variantes : une ligne par choix (nom, prix, stock). Le prix d'une ligne
 * vide reprend le prix principal.
 */
class ProductController extends Controller
{
    public const MAX_PHOTOS = 10;

    public const MAX_VARIANTS = 30;

    public function index(Request $request): View
    {
        $store = $this->currentStore($request);

        return view('admin.products.index', [
            'products' => $store->products()->with('variants', 'category', 'firstImage')->orderBy('position')->orderByDesc('id')->get(),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if (! $this->currentStore($request)->canAddProduct()) {
            return redirect()->route('admin.subscription')->with('status', $this->limitMessage($request));
        }

        return view('admin.products.form', [
            'product' => new Product(['is_visible' => true]),
            'categories' => $this->currentStore($request)->categories,
        ]);
    }

    public function store(Request $request, ImageProcessor $images): RedirectResponse
    {
        $store = $this->currentStore($request);
        if (! $store->canAddProduct()) {
            return redirect()->route('admin.subscription')->with('status', $this->limitMessage($request));
        }
        $data = $this->validated($request);

        $product = DB::transaction(function () use ($store, $data) {
            $product = $store->products()->create([
                'category_id' => $this->categoryId($store, $data['category'] ?? null),
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($store, $data['name']),
                'description' => $data['description'] ?? null,
                'is_visible' => true,
                'track_stock' => $data['track_stock'],
                'addons' => $data['addons'],
            ]);
            $this->syncVariants($store, $product, $data['variants']);

            return $product;
        });

        $this->storePhotos($request, $store, $product, $images);

        return redirect()->route('admin.products.index')->with('status', 'Produit ajouté.');
    }

    public function edit(Request $request, int $product): View
    {
        $store = $this->currentStore($request);

        return view('admin.products.form', [
            'product' => $store->products()->with('variants', 'category', 'images')->findOrFail($product),
            'categories' => $store->categories,
        ]);
    }

    public function update(Request $request, int $product, ImageProcessor $images): RedirectResponse
    {
        $store = $this->currentStore($request);
        $model = $store->products()->with('variants', 'images')->findOrFail($product);
        $data = $this->validated($request, $model);

        DB::transaction(function () use ($store, $model, $data, $request) {
            $model->update([
                'category_id' => $this->categoryId($store, $data['category'] ?? null),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_visible' => $request->boolean('is_visible'),
                'track_stock' => $data['track_stock'],
                'addons' => $data['addons'],
            ]);
            $this->syncVariants($store, $model, $data['variants']);
        });

        // Photos retirées : supprimées du disque aussi.
        $remove = collect($request->input('remove_images', []))->map(fn ($id) => (int) $id);
        foreach ($model->images->whereIn('id', $remove) as $image) {
            Storage::disk('public')->delete([$image->path, $image->thumb_path, ImageProcessor::previewPath($image->path)]);
            $image->delete();
        }

        $this->storePhotos($request, $store, $model, $images);

        return redirect()->route('admin.products.index')->with('status', 'Produit mis à jour.');
    }

    /**
     * @return array{name:string, category:?string, description:?string, track_stock:bool,
     *               variants: list<array{id:?int, name:string, price:int, stock:?int}>,
     *               addons: ?list<array{key:string, name:string, price:int, text:?string}>}
     */
    private function validated(Request $request, ?Product $product = null): array
    {
        $digits = fn ($v) => ($c = preg_replace('/\D+/', '', (string) $v)) === '' ? null : $c;

        $rows = collect($request->input('variants', []))
            ->filter(fn ($r) => is_array($r) && trim((string) ($r['name'] ?? '')) !== '')
            ->map(fn ($r) => [
                'id' => isset($r['id']) && $r['id'] !== '' ? (int) $r['id'] : null,
                'name' => trim((string) $r['name']),
                'price' => $digits($r['price'] ?? null),
                'stock' => $digits($r['stock'] ?? null),
            ])
            ->values();

        // Suppléments payants (ex. flocage) : une ligne sans nom est ignorée.
        $addonRows = collect($request->input('addons', []))
            ->filter(fn ($r) => is_array($r) && trim((string) ($r['name'] ?? '')) !== '')
            ->map(fn ($r) => [
                'key' => is_string($r['key'] ?? null) ? $r['key'] : '',
                'name' => Str::squish((string) $r['name']),
                'price' => $digits($r['price'] ?? null) ?? '0',
                'text' => Str::squish((string) ($r['text'] ?? '')),
            ])
            ->values();

        $request->merge([
            'price' => $digits($request->input('price')),
            'stock' => $digits($request->input('stock')),
            'variant_rows' => $rows->all(),
            'addon_rows' => $addonRows->all(),
        ]);

        $existingPhotos = $product ? $product->images->whereNotIn('id', (array) $request->input('remove_images', []))->count() : 0;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'price' => ['required', 'integer', 'min:1', 'max:10000000000'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'category' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
            'variant_rows' => ['array', 'max:'.self::MAX_VARIANTS],
            'variant_rows.*.name' => ['required', 'string', 'max:80', 'distinct:ignore_case'],
            'variant_rows.*.price' => ['nullable', 'integer', 'min:1', 'max:10000000000'],
            'variant_rows.*.stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'addon_rows' => ['array', 'max:'.Product::MAX_ADDONS],
            'addon_rows.*.name' => ['required', 'string', 'max:60', 'distinct:ignore_case'],
            'addon_rows.*.price' => ['integer', 'min:0', 'max:10000000'],
            'addon_rows.*.text' => ['nullable', 'string', 'max:60'],
            'photos' => ['array', 'max:'.max(0, self::MAX_PHOTOS - $existingPhotos)],
            'photos.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
        ], [
            'price.required' => 'Indiquez le prix en GNF.',
            'price.min' => 'Le prix doit être supérieur à 0.',
            'variant_rows.*.name.distinct' => 'Deux choix portent le même nom.',
            'addon_rows.max' => 'Maximum '.Product::MAX_ADDONS.' suppléments par produit.',
            'addon_rows.*.name.distinct' => 'Deux suppléments portent le même nom.',
            'addon_rows.*.name.max' => 'Nom du supplément : 60 caractères maximum.',
            'addon_rows.*.price.max' => 'Prix du supplément trop élevé.',
            'addon_rows.*.text.max' => 'Texte demandé : 60 caractères maximum.',
            'photos.max' => 'Maximum '.self::MAX_PHOTOS.' photos par produit.',
            'photos.*.image' => 'Ce fichier n\'est pas une photo.',
            'photos.*.mimes' => 'Photo en JPG, PNG ou WebP seulement.',
            'photos.*.max' => 'Photo trop lourde (8 Mo maximum).',
        ]);

        if ($rows->isEmpty()) {
            $variants = [['id' => $product?->variants->first()?->id, 'name' => 'Standard', 'price' => (int) $data['price'], 'stock' => $data['stock']]];
        } else {
            $variants = $rows->map(fn ($r) => [
                'id' => $r['id'],
                'name' => $r['name'],
                'price' => (int) ($r['price'] ?? $data['price']),
                'stock' => $r['stock'],
            ])->all();
        }

        // On suit le stock dès qu'une quantité est renseignée ; les lignes vides valent alors 0.
        $track = collect($variants)->contains(fn ($v) => $v['stock'] !== null);
        foreach ($variants as &$v) {
            $v['stock'] = $track ? (int) ($v['stock'] ?? 0) : null;
        }

        // Clé gardée d'une modification à l'autre (les paniers en cours y font référence) ;
        // une clé inconnue ou en double devient une nouvelle clé.
        $known = $product?->addonList() ?? collect();
        $used = [];
        $addons = $addonRows->map(function ($r) use ($known, &$used) {
            $key = $known->has($r['key']) && ! isset($used[$r['key']]) ? $r['key'] : null;
            while ($key === null || isset($used[$key]) || $known->has($key) && $key !== $r['key']) {
                $key = Str::lower(Str::random(8));
            }
            $used[$key] = true;

            return ['key' => $key, 'name' => $r['name'], 'price' => (int) $r['price'], 'text' => $r['text'] !== '' ? $r['text'] : null];
        })->all();

        return ['name' => $data['name'], 'category' => $data['category'] ?? null, 'description' => $data['description'] ?? null,
            'track_stock' => $track, 'variants' => $variants, 'addons' => $addons ?: null];
    }

    /** Crée, met à jour ou retire les variantes, et journalise les changements de stock. */
    private function syncVariants(Store $store, Product $product, array $rows): void
    {
        $existing = $product->variants()->get()->keyBy('id');
        $kept = [];

        foreach (array_values($rows) as $position => $row) {
            $variant = $row['id'] ? $existing->get($row['id']) : null;
            if ($row['id'] && ! $variant) {
                throw ValidationException::withMessages(['variants' => 'Une variante a été modifiée ailleurs. Rechargez la page.']);
            }

            $before = $variant?->stock;
            $attrs = ['name' => $row['name'], 'price' => $row['price'], 'stock' => $row['stock'], 'position' => $position];
            $variant ? $variant->update($attrs) : ($variant = $product->variants()->create($attrs));
            $kept[] = $variant->id;

            $delta = (int) $row['stock'] - (int) $before;
            if ($row['stock'] !== null && $delta !== 0) {
                StockMovement::create([
                    'store_id' => $store->id, 'variant_id' => $variant->id,
                    'delta' => $delta, 'reason' => $before === null ? 'restock' : 'adjust',
                ]);
            }
        }

        $product->variants()->whereNotIn('id', $kept)->delete();
    }

    private function storePhotos(Request $request, Store $store, Product $product, ImageProcessor $images): void
    {
        $position = (int) $product->images()->max('position');
        foreach ((array) $request->file('photos', []) as $file) {
            $stored = $images->storeProductPhoto($file, 'stores/'.$store->id.'/products');
            $product->images()->create($stored + ['position' => ++$position]);
        }
    }

    private function categoryId(Store $store, ?string $name): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        return $store->categories()->firstOrCreate(
            ['name' => $name],
            ['position' => (int) $store->categories()->max('position') + 1],
        )->id;
    }

    private function uniqueSlug(Store $store, string $name): string
    {
        $base = Str::slug($name) ?: 'produit';
        $slug = $base;
        $i = 2;
        while ($store->products()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    private function limitMessage(Request $request): string
    {
        return 'La formule Découverte permet '.$this->currentStore($request)->maxProducts()
            .' produits. Vos produits restent en ligne ; passez à Commerçant pour en ajouter d\'autres.';
    }

    private function currentStore(Request $request): Store
    {
        return $request->attributes->get('store');
    }
}
