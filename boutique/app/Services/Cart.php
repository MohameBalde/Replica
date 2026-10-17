<?php

namespace App\Services;

use App\Models\Store;
use App\Models\Variant;
use Illuminate\Support\Collection;

/**
 * Panier en session, un par boutique : [variant_id => quantité].
 * Pas de compte client, et ça marche sans JavaScript.
 */
class Cart
{
    public const MAX_QUANTITY = 99;

    public function __construct(private Store $store) {}

    private function key(): string
    {
        return 'cart.'.$this->store->id;
    }

    /** @return array<int,int> */
    public function raw(): array
    {
        return session($this->key(), []);
    }

    public function add(Variant $variant, int $quantity): void
    {
        $cart = $this->raw();
        $cart[$variant->id] = min(self::MAX_QUANTITY, ($cart[$variant->id] ?? 0) + max(1, $quantity));
        session([$this->key() => $cart]);
    }

    public function set(int $variantId, int $quantity): void
    {
        $cart = $this->raw();
        if ($quantity <= 0) {
            unset($cart[$variantId]);
        } else {
            $cart[$variantId] = min(self::MAX_QUANTITY, $quantity);
        }
        session([$this->key() => $cart]);
    }

    public function clear(): void
    {
        session()->forget($this->key());
    }

    /**
     * Lignes du panier avec les variantes chargées depuis la base, filtrées sur
     * cette boutique et sur les produits visibles. Les prix viennent toujours
     * de la base, jamais de la session.
     *
     * @return Collection<int, array{variant: Variant, quantity: int, line_total: int}>
     */
    public function lines(): Collection
    {
        $raw = $this->raw();
        if ($raw === []) {
            return collect();
        }

        $variants = Variant::with('product.firstImage')
            ->whereIn('id', array_keys($raw))
            ->whereHas('product', fn ($q) => $q->where('store_id', $this->store->id)->where('is_visible', true))
            ->get()
            ->keyBy('id');

        return collect($raw)
            ->filter(fn ($qty, $id) => $variants->has($id))
            ->map(fn ($qty, $id) => [
                'variant' => $variants[$id],
                'quantity' => (int) $qty,
                'line_total' => $variants[$id]->price * (int) $qty,
            ])
            ->values();
    }

    public function count(): int
    {
        return (int) array_sum($this->raw());
    }

    public function subtotal(): int
    {
        return (int) $this->lines()->sum('line_total');
    }
}
