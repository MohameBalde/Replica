<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Models\Variant;
use App\Services\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
            // Stock par variante : un maillot L sur deux lignes (deux noms floqués) compte deux fois.
            'inCart' => $lines->groupBy(fn ($l) => $l['variant']->id)->map(fn ($g) => (int) $g->sum('quantity')),
        ]);
    }

    public function add(Request $request, Store $store): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['required', 'integer'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.Cart::MAX_QUANTITY],
            'addons' => ['nullable', 'array', 'max:'.Product::MAX_ADDONS],
            'addons.*' => ['string', 'max:20'],
            'addon_text' => ['nullable', 'array', 'max:'.Product::MAX_ADDONS],
            'addon_text.*' => ['nullable', 'string', 'max:'.Product::ADDON_TEXT_MAX],
        ], [
            'addon_text.*.max' => 'Texte trop long ('.Product::ADDON_TEXT_MAX.' caractères maximum).',
        ]);

        // La variante doit appartenir à un produit visible de CETTE boutique.
        $variant = Variant::whereKey($data['variant_id'])
            ->whereHas('product', fn ($q) => $q->where('store_id', $store->id)->where('is_visible', true))
            ->with('product')
            ->firstOrFail();

        // Suppléments cochés : ceux du produit seulement, avec le texte demandé (ex. le nom à floquer).
        $offered = $variant->product->addonList();
        $addons = [];
        foreach (array_unique($data['addons'] ?? []) as $key) {
            $addon = $offered->get($key);
            if (! $addon) {
                return back()->withInput()->withErrors(['addons' => 'Un supplément n\'est plus proposé. Vérifiez votre choix.']);
            }
            $text = '';
            if ($addon['text'] !== null) {
                $text = (string) Str::squish((string) ($data['addon_text'][$key] ?? '')); // null si l'UTF-8 est invalide
                if ($text === '') {
                    return back()->withInput()->withErrors(['addon_text.'.$key => 'Indiquez le texte pour « '.$addon['name'].' » : '.$addon['text'].'.']);
                }
            }
            $addons[$key] = $text;
        }
        // Sans JavaScript, le champ texte est toujours visible : un texte tapé sans cocher la case n'est pas perdu en silence.
        foreach ($data['addon_text'] ?? [] as $key => $text) {
            $addon = $offered->get((string) $key);
            if ($addon && $addon['text'] !== null && ! isset($addons[$key]) && (string) Str::squish((string) $text) !== '') {
                return back()->withInput()->withErrors(['addon_text.'.$key => 'Cochez « '.$addon['name'].' » pour l\'ajouter, ou videz le texte.']);
            }
        }

        $quantity = $data['quantity'] ?? 1;
        $cart = new Cart($store);
        $already = $cart->quantityOf($variant->id);

        if (! $variant->isAvailable($already + $quantity)) {
            return back()->withInput()->withErrors(['quantity' => $variant->stock > 0
                ? "Plus que {$variant->stock} en stock."
                : 'Ce produit est épuisé.']);
        }

        if (! $cart->add($variant, $quantity, $addons)) {
            return back()->withInput()->withErrors(['quantity' => 'Votre panier est plein ('.Cart::MAX_LINES.' articles différents). Commandez d\'abord ceux-ci.']);
        }

        return back()->with('status', '« '.$variant->product->name.' » ajouté au panier.');
    }

    public function update(Request $request, Store $store): RedirectResponse
    {
        $data = $request->validate([
            'line' => ['required_without:variant_id', 'nullable', 'string', 'max:40'],
            'variant_id' => ['required_without:line', 'nullable', 'integer'], // page du panier ouverte avant les suppléments
            'quantity' => ['required', 'integer', 'min:0', 'max:'.Cart::MAX_QUANTITY],
        ]);

        (new Cart($store))->set($data['line'] ?? 'v'.$data['variant_id'], $data['quantity']);

        return redirect()->route('shop.cart', $store);
    }
}
