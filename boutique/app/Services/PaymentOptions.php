<?php

namespace App\Services;

use App\Models\DeliveryMethod;
use App\Models\PaymentMethod;
use App\Models\Store;
use Illuminate\Support\Collection;

/**
 * Quels moyens de paiement proposer au client pour cette commande.
 *
 * Paiement à la livraison : proposé seulement si le commerçant l'a activé,
 * si le total est sous son plafond, si le mode de livraison l'autorise et si
 * le client n'est pas bloqué. Utilisé pour l'affichage ET vérifié à nouveau
 * à la création de la commande.
 */
class PaymentOptions
{
    /** @return Collection<int, PaymentMethod> */
    public static function available(Store $store, ?DeliveryMethod $delivery, int $total, ?string $customerPhone = null): Collection
    {
        return $store->paymentMethods()
            ->where('is_active', true)
            ->get()
            ->filter(fn (PaymentMethod $m) => $m->isComplete())
            ->filter(fn (PaymentMethod $m) => ! $m->isCashOnDelivery()
                || self::cashOnDeliveryRefusal($store, $m, $delivery, $total, $customerPhone) === null)
            ->values();
    }

    /**
     * Pourquoi le paiement à la livraison est refusé, ou null s'il est permis.
     * Le texte est montré au client quand c'est utile.
     */
    public static function cashOnDeliveryRefusal(Store $store, PaymentMethod $cod, ?DeliveryMethod $delivery, int $total, ?string $customerPhone = null): ?string
    {
        if (! $cod->is_active) {
            return 'Cette boutique demande le paiement avant la livraison.';
        }

        if ($cod->max_order_total !== null && $total > $cod->max_order_total) {
            return 'Paiement à la livraison possible jusqu\'à '.gnf($cod->max_order_total).'.';
        }

        if ($delivery !== null && ! $delivery->allows_cash_on_delivery) {
            return 'Paiement à la livraison non disponible pour « '.$delivery->name.' ».';
        }

        if ($customerPhone !== null
            && $store->customers()->where('phone', $customerPhone)->where('cash_on_delivery_blocked', true)->exists()) {
            return 'Paiement avant livraison requis pour cette commande.';
        }

        return null;
    }
}
