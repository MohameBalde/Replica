<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Cart;
use App\Services\OrderPlacer;
use App\Services\PaymentOptions;
use App\Services\SourceTracker;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    /** S04 : informations, livraison, paiement. */
    public function show(Store $store): View|RedirectResponse
    {
        $cart = new Cart($store);
        $lines = $cart->lines();
        if ($lines->isEmpty()) {
            return redirect()->route('shop.cart', $store);
        }

        $subtotal = (int) $lines->sum('line_total');
        $deliveryMethods = $store->deliveryMethods()->where('is_active', true)->get();

        // Pour chaque mode de livraison : les moyens de paiement permis et la raison
        // d'un éventuel refus du paiement à la livraison. Alpine s'en sert pour
        // masquer les options ; le serveur revérifie de toute façon.
        $cod = $store->cashOnDelivery();
        $options = $deliveryMethods->mapWithKeys(function ($d) use ($store, $subtotal, $cod) {
            $total = $subtotal + $d->fee;

            return [$d->id => [
                'fee' => $d->fee,
                'total' => $total,
                'payments' => PaymentOptions::available($store, $d, $total)->pluck('id')->all(),
                'codNote' => $cod && $cod->is_active ? PaymentOptions::cashOnDeliveryRefusal($store, $cod, $d, $total) : null,
                'needsAddress' => $d->needsAddress(),
                'minOrder' => $d->min_order,
            ]];
        });

        $paymentMethods = $store->paymentMethods()->where('is_active', true)->get()->filter->isComplete()->values();

        return view('shop.checkout', compact('store', 'lines', 'subtotal', 'deliveryMethods', 'paymentMethods', 'options'));
    }

    public function store(Request $request, Store $store, OrderPlacer $placer): RedirectResponse
    {
        $request->merge(['phone' => Phone::normalize($request->input('phone')) ?? $request->input('phone')]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^2246\d{8}$/'],
            'delivery_method_id' => ['required', 'integer'],
            'payment_method_id' => ['required', 'integer'],
            'address' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'name.required' => 'Indiquez votre nom.',
            'phone.required' => 'Indiquez votre numéro WhatsApp.',
            'phone.regex' => 'Entrez un numéro guinéen valable, par exemple 620 00 00 00.',
            'delivery_method_id.required' => 'Choisissez un mode de livraison.',
            'payment_method_id.required' => 'Choisissez un moyen de paiement.',
        ]);

        if (! $store->is_open) {
            return back()->withErrors(['cart' => 'La boutique est fermée pour le moment.'])->withInput();
        }

        $cart = new Cart($store);
        $source = SourceTracker::current($store);
        $order = $placer->place($store, $cart, $data, $source['source'], $source['campaign']);
        $cart->clear();

        return redirect()->route('invoice.show', $order)->with('just_ordered', true);
    }
}
