<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Variant;
use App\Services\OrderPlacer;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Commande saisie à la main : reçue par téléphone, en message ou vendue au comptoir.
 * Elle compte comme les autres (numéro, stock, chiffres de l'Accueil, fiche client).
 */
class ManualOrderController extends Controller
{
    public const MAX_LINES = 30;

    public const STATUSES = [
        'confirmed' => 'Confirmée : à préparer ou à livrer',
        'completed' => 'Terminée : déjà remise au client',
        'pending' => 'En attente : à confirmer plus tard',
    ];

    public function create(Request $request): View
    {
        $store = $this->currentStore($request);

        return view('admin.orders.create', [
            'products' => $store->products()->with('variants')->orderBy('name')->get(),
            'deliveryMethods' => $store->deliveryMethods()->orderByDesc('is_active')->orderBy('id')->get(),
            'paymentMethods' => $store->paymentMethods()->orderByDesc('is_active')->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request, OrderPlacer $placer): RedirectResponse
    {
        $store = $this->currentStore($request);
        $request->merge(['phone' => Phone::normalize($request->input('phone')) ?? $request->input('phone')]);
        // Espaces, retours à la ligne et espaces insécables regroupés : ces textes partent dans des messages WhatsApp.
        $request->merge(collect($request->only('name', 'address'))->map(fn ($v) => is_string($v) ? Str::squish($v) : $v)->all());

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^2246\d{8}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'internal_note' => ['nullable', 'string', 'max:500'],
            'delivery_method_id' => ['required', 'integer'],
            'payment_method_id' => ['required', 'integer'],
            'status' => ['required', Rule::in(array_keys(self::STATUSES))],
            'paid' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'max:'.self::MAX_LINES],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
            'items.*.price' => ['nullable', 'string', 'max:20'],
        ], [
            'name.required' => 'Indiquez le nom du client.',
            'phone.required' => 'Indiquez le numéro WhatsApp du client.',
            'phone.regex' => 'Entrez un numéro guinéen valable, par exemple 620 00 00 00.',
            'delivery_method_id.required' => 'Choisissez un mode de livraison.',
            'payment_method_id.required' => 'Choisissez un moyen de paiement.',
            'items.required' => 'Ajoutez au moins un article.',
            'items.*.quantity.min' => 'La quantité doit être d\'au moins 1.',
            'items.*.quantity.max' => 'Au plus 999 par ligne.',
        ]);

        // Lignes remplies seulement ; chaque article doit être un produit de CETTE boutique.
        $rows = collect($data['items'])->filter(fn ($r) => filled($r['variant_id'] ?? null));
        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Ajoutez au moins un article.']);
        }
        $variants = Variant::with('product')->whereIn('id', $rows->pluck('variant_id'))
            ->whereHas('product', fn ($q) => $q->where('store_id', $store->id))->get()->keyBy('id');

        $lines = $rows->map(function ($r) use ($variants) {
            $variant = $variants->get((int) $r['variant_id']) ?? throw ValidationException::withMessages(['items' => 'Un article choisi n\'existe plus. Rechargez la page.']);
            $price = preg_replace('/\D+/', '', (string) ($r['price'] ?? ''));
            if (strlen($price) > 10) {
                throw ValidationException::withMessages(['items' => 'Prix trop grand pour « '.$variant->product->name.' ».']);
            }

            return [
                'variant' => $variant,
                'quantity' => (int) ($r['quantity'] ?? 1) ?: 1,
                'unit_price' => $price === '' ? $variant->price : (int) $price, // vide = prix du catalogue
            ];
        })->values();

        $order = $placer->placeManual($store, $lines, [
            'name' => $data['name'],
            'phone' => $data['phone'],
            'address' => $data['address'] ?? null,
            'internal_note' => $data['internal_note'] ?? null,
            'delivery_method_id' => (int) $data['delivery_method_id'],
            'payment_method_id' => (int) $data['payment_method_id'],
            'status' => $data['status'],
            'paid' => (bool) ($data['paid'] ?? false),
        ]);

        return redirect()->route('admin.orders.show', $order->id)->with('status', 'Commande #'.$order->number.' enregistrée.');
    }

    private function currentStore(Request $request): Store
    {
        return $request->attributes->get('store');
    }
}
