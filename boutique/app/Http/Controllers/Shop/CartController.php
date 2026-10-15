<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Variant;
use App\Services\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    /** S03 : panier. */
    public function show(Store $store): View
    {
        $cart = new Cart($store);
        $lines = $cart->lines();

        return view('shop.cart', [
            'store' => $store,
            'lines' => $lines,
            'subtotal' => (int) $lines->sum('line_total'),
        ]);
    }

    public function add(Request $request, Store $store): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.Cart::MAX_QUANTITY],
        ]);

        // La variante doit appartenir à un produit visible de CETTE boutique.
        $variant = Variant::whereKey($data['variant_id'])
            ->whereHas('product', fn ($q) => $q->where('store_id', $store->id)->where('is_visible', true))
            ->with('product')
            ->firstOrFail();

        $quantity = $data['quantity'] ?? 1;
        $cart = new Cart($store);
        $already = $cart->raw()[$variant->id] ?? 0;

        if (! $variant->isAvailable($already + $quantity)) {
            return back()->withErrors(['quantity' => $variant->stock > 0
                ? "Plus que {$variant->stock} en stock."
                : 'Ce produit est épuisé.']);
        }

        $cart->add($variant, $quantity);

        return back()->with('status', '« '.$variant->product->name.' » ajouté au panier.');
    }

    public function update(Request $request, Store $store): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:0', 'max:'.Cart::MAX_QUANTITY],
        ]);

        (new Cart($store))->set($data['variant_id'], $data['quantity']);

        return redirect()->route('shop.cart', $store);
    }
}
