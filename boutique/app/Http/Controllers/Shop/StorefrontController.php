<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Services\Cart;
use App\Services\SourceTracker;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    /** S01 : vitrine. */
    public function show(Request $request, Store $store): View
    {
        SourceTracker::capture($request, $store);

        $categories = $store->categories()->whereHas('products', fn ($q) => $q->where('is_visible', true))->get();
        $categoryId = $categories->firstWhere('id', (int) $request->query('cat'))?->id;

        $products = $store->products()
            ->where('is_visible', true)
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->with('variants', 'firstImage')
            ->orderBy('position')->orderByDesc('id')
            ->get();

        $cart = new Cart($store);

        return view('shop.show', [
            'store' => $store,
            'categories' => $categories,
            'categoryId' => $categoryId,
            'products' => $products,
            'cartCount' => $cart->count(),
            'cartTotal' => $cart->subtotal(),
        ]);
    }

    /** S02 : fiche produit (lien direct utilisable dans une pub). */
    public function product(Request $request, Store $store, Product $product): View
    {
        abort_unless($product->is_visible, 404);
        SourceTracker::capture($request, $store);

        $product->load('variants', 'images');
        $cart = new Cart($store);

        return view('shop.product', [
            'store' => $store,
            'product' => $product,
            'cartCount' => $cart->count(),
            'cartTotal' => $cart->subtotal(),
        ]);
    }
}
