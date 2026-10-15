<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Models\Store;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * S19 : moyens de paiement. Le commerçant active ou bloque le paiement à la
 * livraison, avec un plafond optionnel, et renseigne ses numéros mobile money.
 */
class PaymentSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        $store = $this->currentStore($request);

        return view('admin.settings.payments', [
            'methods' => $store->paymentMethods()->get(),
            'cod' => $store->cashOnDelivery(),
            'deliveryMethods' => $store->deliveryMethods()->where('is_active', true)->get(),
        ]);
    }

    public function updateCashOnDelivery(Request $request): RedirectResponse
    {
        $store = $this->currentStore($request);
        $cod = $store->paymentMethods()->firstOrCreate(['type' => PaymentMethod::CASH_ON_DELIVERY]);

        $request->merge(['max_order_total' => preg_replace('/\D+/', '', (string) $request->input('max_order_total')) ?: null]);

        $data = $request->validate([
            'max_order_total' => [$request->boolean('limit') && $request->boolean('is_active') ? 'required' : 'nullable', 'integer', 'min:1000'],
        ], [
            'max_order_total.required' => 'Indiquez le montant maximum, ou décochez la limite.',
            'max_order_total.min' => 'Le plafond doit être d\'au moins 1 000 GNF.',
        ]);

        $active = $request->boolean('is_active');
        if (! $active && ! $this->hasOtherActiveMethod($store, $cod)) {
            return back()->withErrors(['is_active' => 'Gardez au moins un moyen de paiement actif. Ajoutez d\'abord votre numéro Orange Money ou MTN.']);
        }

        $cod->update([
            'is_active' => $active,
            'max_order_total' => $request->boolean('limit') ? $data['max_order_total'] : null,
        ]);

        return back()->with('status', $active ? 'Paiement à la livraison activé.' : 'Paiement à la livraison bloqué.');
    }

    public function updateMobileMoney(Request $request, string $type): RedirectResponse
    {
        abort_unless(in_array($type, [PaymentMethod::ORANGE_MONEY, PaymentMethod::MTN_MOMO], true), 404);

        $store = $this->currentStore($request);
        $method = $store->paymentMethods()->firstOrCreate(['type' => $type], ['is_active' => false]);

        $request->merge(['account_number' => Phone::normalize($request->input('account_number')) ?? $request->input('account_number')]);
        $data = $request->validate([
            'account_number' => ['nullable', 'regex:/^2246\d{8}$/'],
            'account_name' => ['nullable', 'string', 'max:120'],
        ], ['account_number.regex' => 'Entrez un numéro guinéen valable.']);

        $active = $request->boolean('is_active');
        if ($active && blank($data['account_number'])) {
            return back()->withErrors(["{$type}.account_number" => 'Ajoutez le numéro pour activer ce moyen de paiement.']);
        }
        if (! $active && $method->is_active && ! $this->hasOtherActiveMethod($store, $method)) {
            return back()->withErrors(['is_active' => 'Gardez au moins un moyen de paiement actif.']);
        }

        $method->update($data + ['is_active' => $active]);

        return back()->with('status', $method->label().' enregistré.');
    }

    private function hasOtherActiveMethod(Store $store, PaymentMethod $except): bool
    {
        return $store->paymentMethods()->where('id', '!=', $except->id)->where('is_active', true)->get()
            ->contains(fn (PaymentMethod $m) => $m->isComplete());
    }

    private function currentStore(Request $request): Store
    {
        return $request->attributes->get('store');
    }
}
