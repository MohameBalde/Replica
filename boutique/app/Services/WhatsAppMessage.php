<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Store;
use App\Models\SubscriptionPayment;
use App\Support\Plans;

/**
 * Message de commande envoyé par le client au commerçant via un lien wa.me.
 * Court (les liens très longs échouent sur certains téléphones) : le détail
 * complet est dans la facture, dont le lien termine le message.
 */
class WhatsAppMessage
{
    public const MAX_ITEMS_LISTED = 8;

    public static function forOrder(Order $order): string
    {
        $order->loadMissing('items', 'store');

        $lines = ['Bonjour '.$order->store->name.' 👋', 'Voici ma commande *#'.$order->number.'* :', ''];

        foreach ($order->items->take(self::MAX_ITEMS_LISTED) as $item) {
            $lines[] = '• '.$item->quantity.' × '.$item->label().' — '.gnf($item->line_total);
        }
        if ($order->items->count() > self::MAX_ITEMS_LISTED) {
            $lines[] = '• … et '.($order->items->count() - self::MAX_ITEMS_LISTED).' autre(s) article(s)';
        }

        $lines[] = '';
        if ($order->delivery_fee > 0) {
            $lines[] = 'Livraison : '.gnf($order->delivery_fee);
        }
        $lines[] = '*Total : '.gnf($order->total).'*';
        $lines[] = '';
        $lines[] = 'Nom : '.$order->customer_name;
        $lines[] = 'Service : '.$order->delivery_method_name;
        if ($order->address) {
            $lines[] = 'Adresse : '.$order->address;
        }
        $lines[] = $order->isCashOnDelivery()
            ? 'Paiement : à la livraison ('.gnf($order->total).' à préparer)'
            : 'Paiement : '.$order->paymentLabel();
        $lines[] = '';
        $lines[] = 'Détail : '.route('invoice.show', $order);

        return implode("\n", $lines);
    }

    /**
     * Message de l'équipe Wanllan au commerçant : une relance si sa formule
     * finit bientôt ou est finie, sinon une simple prise de contact.
     */
    public static function toMerchant(Store $store): string
    {
        $hello = trim('Bonjour '.($store->user?->name ?? '')).' 👋 Ici Wanllan.';
        if (! $store->needsReminder()) {
            return $hello.' Je vous écris à propos de votre boutique « '.$store->name.' » ('.route('shop.show', $store).').';
        }

        $plan = Plans::get($store->plan);
        $date = $store->plan_expires_at->timezone('Africa/Conakry')->format('d/m/Y');
        $howToPay = gnf($plan['monthly']).' par mois (ou '.gnf(Plans::yearly($store->plan)).' par an) par Orange Money au '
            .phone_display(config('wanllan.pay_orange_money')).' ('.config('wanllan.pay_name').'), puis envoyez-nous la capture ici.';

        return $store->plan_expires_at->isFuture()
            ? $hello.' Votre formule '.$plan['name'].' pour « '.$store->name.' » se termine le '.$date.'. Pour continuer sans interruption : '.$howToPay.' Merci !'
            : $hello.' Votre formule '.$plan['name'].' pour « '.$store->name.' » est terminée depuis le '.$date
                .' : la boutique est repassée en Découverte, rien n\'est perdu. Pour la réactiver : '.$howToPay;
    }

    /** Confirmation envoyée au commerçant après l'enregistrement de son paiement. */
    public static function subscriptionConfirmed(Store $store, SubscriptionPayment $payment): string
    {
        return trim('Bonjour '.($store->user?->name ?? '')).' 👋 Ici Wanllan. Paiement de '.gnf($payment->amount).' bien reçu ✅'
            ."\n".'Votre formule '.Plans::get($payment->plan)['name'].' pour « '.$store->name.' » est active jusqu\'au '
            .$payment->period_end->timezone('Africa/Conakry')->format('d/m/Y').'. Merci de votre confiance !';
    }

    public static function url(string $phone, string $text): string
    {
        return 'https://wa.me/'.$phone.'?text='.rawurlencode($text);
    }
}
