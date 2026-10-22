<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Variant;
use Illuminate\Support\Collection;

/**
 * Panier en session, un par boutique. Une ligne = une variante + ses suppléments
 * (et le texte demandé, ex. le nom à floquer) : deux maillots L avec deux noms
 * différents font deux lignes. Pas de compte client, et ça marche sans JavaScript.
 *
 * Session : [clé de ligne => ['variant' => id, 'qty' => n, 'addons' => [clé du supplément => texte]]].
 */
class Cart
{
    public const MAX_QUANTITY = 99;

    public const MAX_LINES = 30;

    public function __construct(private Store $store) {}

    private function key(): string
    {
        return 'cart.'.$this->store->id;
    }

    /** @return array<string, array{variant:int, qty:int, addons:array<string,string>}> */
    public function raw(): array
    {
        $out = [];
        foreach ((array) session($this->key(), []) as $key => $line) {
            // Ancien format (avant les suppléments) : [variant_id => quantité].
            if (! is_array($line)) {
                $line = ['variant' => (int) $key, 'qty' => (int) $line, 'addons' => []];
            }
            $variant = (int) ($line['variant'] ?? 0);
            $qty = (int) ($line['qty'] ?? 0);
            if ($variant <= 0 || $qty <= 0) {
                continue;
            }
            $addons = array_map('strval', (array) ($line['addons'] ?? []));
            ksort($addons);
            $out[self::lineKey($variant, $addons)] = ['variant' => $variant, 'qty' => min(self::MAX_QUANTITY, $qty), 'addons' => $addons];
        }

        return $out;
    }

    /** « v12 » sans supplément, « v12-<empreinte> » avec : même choix, mêmes suppléments, même texte = même ligne. */
    public static function lineKey(int $variantId, array $addons): string
    {
        if ($addons === []) {
            return 'v'.$variantId;
        }
        ksort($addons);

        return 'v'.$variantId.'-'.substr(sha1(json_encode($addons)), 0, 12);
    }

    /**
     * Ajoute au panier, ou augmente la ligne identique. Faux si le panier a déjà
     * MAX_LINES lignes différentes.
     *
     * @param  array<string,string>  $addons  clé du supplément => texte du client ('' si aucun texte demandé)
     */
    public function add(Variant $variant, int $quantity, array $addons = []): bool
    {
        $cart = $this->raw();
        ksort($addons);
        $key = self::lineKey($variant->id, $addons);
        if (! isset($cart[$key]) && count($cart) >= self::MAX_LINES) {
            return false;
        }
        $cart[$key] = [
            'variant' => $variant->id,
            'qty' => min(self::MAX_QUANTITY, ($cart[$key]['qty'] ?? 0) + max(1, $quantity)),
            'addons' => $addons,
        ];
        session([$this->key() => $cart]);

        return true;
    }

    public function set(string $lineKey, int $quantity): void
    {
        $cart = $this->raw();
        if (! isset($cart[$lineKey])) {
            return;
        }
        if ($quantity <= 0) {
            unset($cart[$lineKey]);
        } else {
            $cart[$lineKey]['qty'] = min(self::MAX_QUANTITY, $quantity);
        }
        session([$this->key() => $cart]);
    }

    /** Quantité de cette variante dans tout le panier : le stock se compte par variante, toutes lignes confondues. */
    public function quantityOf(int $variantId): int
    {
        return (int) collect($this->raw())->where('variant', $variantId)->sum('qty');
    }

    public function clear(): void
    {
        session()->forget($this->key());
    }

    /**
     * Lignes du panier avec les variantes chargées depuis la base, filtrées sur
     * cette boutique et sur les produits visibles. Les prix (variante et
     * suppléments) viennent toujours de la base, jamais de la session ; un
     * supplément retiré par le commerçant (ou dont le texte demandé manque)
     * disparaît de la ligne.
     *
     * @return Collection<int, array{key: string, variant: Variant, quantity: int,
     *     addons: list<array{key:string, name:string, price:int, text:?string}>, unit_price: int, line_total: int}>
     */
    public function lines(): Collection
    {
        $raw = $this->raw();
        if ($raw === []) {
            return collect();
        }

        $variants = Variant::with('product.firstImage')
            ->whereIn('id', array_unique(array_column($raw, 'variant')))
            ->whereHas('product', fn ($q) => $q->where('store_id', $this->store->id)->where('is_visible', true))
            ->get()
            ->keyBy('id');

        return collect($raw)
            ->filter(fn ($line) => $variants->has($line['variant']))
            ->map(function ($line, $key) use ($variants) {
                $variant = $variants[$line['variant']];
                $offered = $variant->product->addonList();
                // Un supplément retiré, ou qui demande maintenant un texte que le client n'a pas donné, sort de la ligne (avec son prix).
                $addons = collect($line['addons'])
                    ->filter(fn ($text, $addonKey) => $offered->has($addonKey) && ($offered[$addonKey]['text'] === null || $text !== ''))
                    ->map(fn ($text, $addonKey) => [
                        'key' => (string) $addonKey,
                        'name' => $offered[$addonKey]['name'],
                        'price' => $offered[$addonKey]['price'],
                        'text' => $offered[$addonKey]['text'] !== null && $text !== '' ? $text : null,
                    ])
                    ->values()->all();
                $unit = $variant->price + (int) array_sum(array_column($addons, 'price'));

                return [
                    'key' => (string) $key,
                    'variant' => $variant,
                    'quantity' => $line['qty'],
                    'addons' => $addons,
                    'unit_price' => $unit,
                    'line_total' => $unit * $line['qty'],
                ];
            })
            ->values();
    }

    public function count(): int
    {
        return (int) array_sum(array_column($this->raw(), 'qty'));
    }

    public function subtotal(): int
    {
        return (int) $this->lines()->sum('line_total');
    }
}
