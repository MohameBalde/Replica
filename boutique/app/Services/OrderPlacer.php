<?php

namespace App\Services;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Variant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Crée une commande en une seule transaction : vérifie livraison et paiement,
 * réserve le stock sans survente, attribue le numéro, enregistre le client.
 */
class OrderPlacer
{
    /**
     * @param  array{name:string, phone:string, address:?string, note:?string, delivery_method_id:int, payment_method_id:int, answers?:list<array{q:string, a:string}>}  $data
     */
    public function place(Store $store, Cart $cart, array $data, string $source = 'direct', ?string $campaign = null): Order
    {
        $lines = $cart->lines();
        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Votre panier est vide.']);
        }

        $delivery = $store->deliveryMethods()->where('is_active', true)->find($data['delivery_method_id']);
        if (! $delivery) {
            throw ValidationException::withMessages(['delivery_method_id' => 'Choisissez un mode de livraison.']);
        }

        if ($delivery->needsAddress() && blank($data['address'] ?? null)) {
            throw ValidationException::withMessages(['address' => 'Indiquez votre adresse de livraison.']);
        }

        $subtotal = (int) $lines->sum('line_total');
        if ($subtotal < $delivery->min_order) {
            throw ValidationException::withMessages([
                'delivery_method_id' => 'Minimum de commande pour cette livraison : '.gnf($delivery->min_order).'.',
            ]);
        }

        $total = $subtotal + $delivery->fee;

        // Le moyen de paiement doit être permis pour CETTE commande, pas seulement affiché.
        $payment = PaymentOptions::available($store, $delivery, $total, $data['phone'])
            ->firstWhere('id', (int) $data['payment_method_id']);
        if (! $payment) {
            $cod = $store->cashOnDelivery();
            $reason = $cod && (int) $data['payment_method_id'] === $cod->id
                ? PaymentOptions::cashOnDeliveryRefusal($store, $cod, $delivery, $total, $data['phone'])
                : null;
            throw ValidationException::withMessages([
                'payment_method_id' => $reason ?? 'Choisissez un moyen de paiement.',
            ]);
        }

        $address = $delivery->needsAddress() ? $data['address'] : null;

        return $this->persist($store, $lines, $data['name'], $data['phone'], $address, [
            'address' => $address,
            'note' => $data['note'] ?? null,
            'answers' => ($data['answers'] ?? []) ?: null,
            'delivery_method_id' => $delivery->id,
            'delivery_method_name' => $delivery->name,
            'delivery_fee' => $delivery->fee,
            'payment_method_id' => $payment->id,
            'payment_method_type' => $payment->type,
            'subtotal' => $subtotal,
            'total' => $total,
            'source' => $source,
            'campaign' => $campaign,
        ], 'cart');
    }

    /**
     * Commande saisie par le commerçant (reçue par téléphone, vendue en boutique…).
     * Il choisit lui-même livraison, paiement et prix : pas de minimum de commande ni
     * de règle « à la livraison ». Même numérotation, même fiche client et même stock
     * que les commandes de la vitrine.
     *
     * @param  Collection<int, array{variant: Variant, quantity: int, unit_price: int}>  $lines
     * @param  array{name:string, phone:string, address:?string, internal_note:?string, delivery_method_id:int, payment_method_id:int, status:string, paid:bool}  $data
     */
    public function placeManual(Store $store, Collection $lines, array $data): Order
    {
        $delivery = $store->deliveryMethods()->find($data['delivery_method_id']);
        if (! $delivery) {
            throw ValidationException::withMessages(['delivery_method_id' => 'Choisissez un mode de livraison.']);
        }
        $payment = $store->paymentMethods()->find($data['payment_method_id']);
        if (! $payment) {
            throw ValidationException::withMessages(['payment_method_id' => 'Choisissez un moyen de paiement.']);
        }
        // Sans numéro, le client ne saurait pas où payer (sauf si c'est déjà payé).
        if (! $payment->isComplete() && ! $data['paid']) {
            throw ValidationException::withMessages(['payment_method_id' => 'Ce moyen de paiement n\'a pas encore de numéro (Réglages > Paiements). Choisissez-en un autre ou cochez « Déjà payée ».']);
        }
        // Comme sur la vitrine : une livraison demande une adresse, un retrait n'en garde pas.
        if ($delivery->needsAddress() && blank($data['address'] ?? null)) {
            throw ValidationException::withMessages(['address' => 'Indiquez l\'adresse de livraison.']);
        }

        $lines = $lines->map(fn (array $line) => $line + ['line_total' => $line['unit_price'] * $line['quantity']]);
        $subtotal = (int) $lines->sum('line_total');
        $address = $delivery->needsAddress() ? $data['address'] : null;
        $completed = $data['status'] === 'completed';

        return $this->persist($store, $lines, $data['name'], $data['phone'], $address, [
            'status' => $data['status'],
            'payment_status' => $data['paid'] ? 'paid' : 'unpaid',
            'fulfillment_status' => $completed ? 'fulfilled' : 'unfulfilled',
            'address' => $address,
            'internal_note' => $data['internal_note'] ?? null,
            'delivery_method_id' => $delivery->id,
            'delivery_method_name' => $delivery->name,
            'delivery_fee' => $delivery->fee,
            'payment_method_id' => $payment->id,
            'payment_method_type' => $payment->type,
            'subtotal' => $subtotal,
            'total' => $subtotal + $delivery->fee,
            'source' => Order::MANUAL,
            // Saisie par le commerçant : il a déjà parlé au client, pas de message WhatsApp à attendre.
            'whatsapp_sent_at' => now(),
        ], 'items');
    }

    /**
     * Écrit la commande en une transaction : numéro suivant sous verrou, fiche client,
     * lignes, et stock décompté sans survente (sinon tout est annulé, erreur sur $errorKey).
     * Une ligne peut porter son propre prix unitaire (commande saisie à la main, ou
     * prix de la variante + suppléments pour le panier) et ses suppléments.
     */
    private function persist(Store $store, Collection $lines, string $name, string $phone, ?string $address, array $attributes, string $errorKey): Order
    {
        return DB::transaction(function () use ($store, $lines, $name, $phone, $address, $attributes, $errorKey) {
            // Numéro lisible par boutique (#1001...), pris sous verrou.
            $lockedStore = Store::whereKey($store->id)->lockForUpdate()->first();
            $number = $lockedStore->next_order_number;
            $lockedStore->increment('next_order_number');

            $customer = $store->customers()->firstOrNew(['phone' => $phone]);
            $customer->name = $name;
            if ($address !== null) {
                $customer->address = $address;
            }
            $customer->save();

            $order = $store->orders()->create($attributes + [
                'customer_id' => $customer->id,
                'number' => $number,
                'public_token' => (string) Str::ulid(),
                'customer_name' => $name,
                'customer_phone' => $phone,
                'currency' => $store->currency,
            ]);

            $taken = []; // déjà retiré par les lignes précédentes de cette commande (même article sur deux lignes)
            foreach ($lines as $line) {
                /** @var Variant $variant */
                $variant = $line['variant'];
                $qty = $line['quantity'];

                if ($variant->stock !== null) {
                    // Décrément conditionnel : aucune ligne touchée = plus assez de stock.
                    $updated = Variant::whereKey($variant->id)->where('stock', '>=', $qty)->decrement('stock', $qty);
                    if ($updated === 0) {
                        $left = (int) Variant::whereKey($variant->id)->value('stock') + ($taken[$variant->id] ?? 0);
                        throw ValidationException::withMessages([
                            $errorKey => $left > 0
                                ? "« {$variant->product->name} » : plus que {$left} en stock."
                                : "« {$variant->product->name} » vient d'être épuisé.",
                        ]);
                    }
                    $taken[$variant->id] = ($taken[$variant->id] ?? 0) + $qty;
                    StockMovement::create([
                        'store_id' => $store->id, 'variant_id' => $variant->id,
                        'order_id' => $order->id, 'delta' => -$qty, 'reason' => 'sale',
                    ]);
                }

                $order->items()->create([
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'variant_name' => $variant->name,
                    // Suppléments copiés tels quels : un changement de prix plus tard ne touche pas la commande.
                    'options' => collect($line['addons'] ?? [])->map(fn ($a) => ['name' => $a['name'], 'price' => $a['price'], 'text' => $a['text']])->values()->all() ?: null,
                    'unit_price' => $line['unit_price'] ?? $variant->price,
                    'quantity' => $qty,
                    'line_total' => $line['line_total'],
                ]);
            }

            return $order;
        });
    }

    /** Annule et remet en stock ce que la commande avait réservé. */
    public function cancel(Order $order): void
    {
        if ($order->isCancelled()) {
            return;
        }

        DB::transaction(function () use ($order) {
            $locked = Order::whereKey($order->id)->lockForUpdate()->first();
            if ($locked->isCancelled()) {
                return;
            }

            $sold = StockMovement::where('order_id', $order->id)->where('reason', 'sale')->get();
            foreach ($sold as $movement) {
                Variant::whereKey($movement->variant_id)->whereNotNull('stock')->increment('stock', -$movement->delta);
                StockMovement::create([
                    'store_id' => $order->store_id, 'variant_id' => $movement->variant_id,
                    'order_id' => $order->id, 'delta' => -$movement->delta, 'reason' => 'cancel',
                ]);
            }

            $locked->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        });

        $order->refresh();
    }
}
