<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $store = $this->currentStore($request);

        return view('admin.products.index', [
            'products' => $store->products()->with('variants', 'category')->orderBy('position')->orderByDesc('id')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.products.form', [
            'product' => new Product(['is_visible' => true]),
            'categories' => $this->currentStore($request)->categories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $store = $this->currentStore($request);
        $data = $this->validated($request);

        DB::transaction(function () use ($store, $data) {
            $product = $store->products()->create([
                'category_id' => $this->categoryId($store, $data['category'] ?? null),
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($store, $data['name']),
                'description' => $data['description'] ?? null,
                'is_visible' => true,
                'track_stock' => $data['stock'] !== null,
            ]);
            $product->variants()->create([
                'name' => 'Standard',
                'price' => $data['price'],
                'stock' => $data['stock'],
            ]);
        });

        return redirect()->route('admin.products.index')->with('status', 'Produit ajouté.');
    }

    public function edit(Request $request, int $product): View
    {
        $store = $this->currentStore($request);

        return view('admin.products.form', [
            'product' => $store->products()->with('variants', 'category')->findOrFail($product),
            'categories' => $store->categories,
        ]);
    }

    public function update(Request $request, int $product): RedirectResponse
    {
        $store = $this->currentStore($request);
        $model = $store->products()->with('variants')->findOrFail($product);
        $data = $this->validated($request);

        DB::transaction(function () use ($store, $model, $data, $request) {
            $model->update([
                'category_id' => $this->categoryId($store, $data['category'] ?? null),
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_visible' => $request->boolean('is_visible'),
                'track_stock' => $data['stock'] !== null,
            ]);
            // V1 : une seule variante "Standard" éditée ici. Les variantes multiples arrivent à l'étape 2.
            $model->variants->first()->update(['price' => $data['price'], 'stock' => $data['stock']]);
        });

        return redirect()->route('admin.products.index')->with('status', 'Produit mis à jour.');
    }

    private function validated(Request $request): array
    {
        $request->merge([
            'price' => $this->digits($request->input('price')),
            'stock' => $request->filled('stock') ? $this->digits($request->input('stock')) : null,
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'price' => ['required', 'integer', 'min:1', 'max:10000000000'],
            'stock' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'category' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'price.required' => 'Indiquez le prix en GNF.',
            'price.min' => 'Le prix doit être supérieur à 0.',
        ]);
    }

    /** "150 000" ou "150.000" -> 150000. */
    private function digits(mixed $value): ?string
    {
        $clean = preg_replace('/\D+/', '', (string) $value);

        return $clean === '' ? null : $clean;
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

    private function currentStore(Request $request): Store
    {
        return $request->attributes->get('store');
    }
}
