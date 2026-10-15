<?php

namespace App\Services;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Variant;
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
     * @param  array{name:string, phone:string, address:?string, note:?string, delivery_method_id:int, payment_method_id:int}  $data
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

        return DB::transaction(function () use ($store, $lines, $data, $delivery, $payment, $subtotal, $total, $source, $campaign) {
            // Numéro lisible par boutique (#1001...), pris sous verrou.
            $lockedStore = Store::whereKey($store->id)->lockForUpdate()->first();
            $number = $lockedStore->next_order_number;
            $lockedStore->increment('next_order_number');

            $customer = $store->customers()->firstOrNew(['phone' => $data['phone']]);
            $customer->name = $data['name'];
            if ($delivery->needsAddress()) {
                $customer->address = $data['address'];
            }
            $customer->save();

            $order = $store->orders()->create([
                'customer_id' => $customer->id,
                'number' => $number,
                'public_token' => (string) Str::ulid(),
                'customer_name' => $data['name'],
                'customer_phone' => $data['phone'],
                'address' => $delivery->needsAddress() ? $data['address'] : null,
                'note' => $data['note'] ?? null,
                'delivery_method_id' => $delivery->id,
                'delivery_method_name' => $delivery->name,
                'delivery_fee' => $delivery->fee,
                'payment_method_id' => $payment->id,
                'payment_method_type' => $payment->type,
                'subtotal' => $subtotal,
                'total' => $total,
                'currency' => $store->currency,
                'source' => $source,
                'campaign' => $campaign,
            ]);

            foreach ($lines as $line) {
                /** @var Variant $variant */
                $variant = $line['variant'];
                $qty = $line['quantity'];

                if ($variant->stock !== null) {
                    // Décrément conditionnel : aucune ligne touchée = plus assez de stock.
                    $updated = Variant::whereKey($variant->id)->where('stock', '>=', $qty)->decrement('stock', $qty);
                    if ($updated === 0) {
                        $left = (int) Variant::whereKey($variant->id)->value('stock');
                        throw ValidationException::withMessages([
                            'cart' => $left > 0
                                ? "« {$variant->product->name} » : plus que {$left} en stock."
                                : "« {$variant->product->name} » vient d'être épuisé.",
                        ]);
                    }
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
                    'unit_price' => $variant->price,
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
