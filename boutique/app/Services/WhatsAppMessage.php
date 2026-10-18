<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Store;
use App\Models\SubscriptionPayment;
use App\Support\Plans;
use Illuminate\Support\Str;

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
        foreach ($order->answers ?? [] as $qa) {
            $lines[] = $qa['q'].' : '.$qa['a'];
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

    /**
     * Messages prêts que le commerçant envoie au client depuis la fiche commande,
     * selon où en est la commande : [clé => [libellé du bouton, texte]].
     * Chaque texte est court et finit, si utile, par le lien de la facture.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function toCustomer(Order $order): array
    {
        $order->loadMissing('store', 'paymentMethod');
        $hello = 'Bonjour '.Str::before(trim($order->customer_name).' ', ' ').', ici '.$order->store->name.' 👋';
        $ref = 'Commande #'.$order->number.' ('.gnf($order->total).')';
        $invoice = 'Détail : '.route('invoice.show', $order);
        $method = $order->paymentMethod;
        $howToPay = $method && filled($method->account_number)
            ? 'envoyez '.gnf($order->total).' par '.$order->paymentLabel().' au '.phone_display($method->account_number)
                .($method->account_name ? ' ('.$method->account_name.')' : '').', puis ajoutez la capture sur le lien ci-dessous.'
            : 'envoyez '.gnf($order->total).' par '.$order->paymentLabel().'.';
        $cash = $order->isCashOnDelivery() && $order->payment_status === 'unpaid';

        if ($order->isCancelled()) {
            return ['cancelled' => ['Prévenir de l\'annulation', $hello."\n".$ref.' : votre commande a été annulée. '
                .'Écrivez-nous si vous voulez la refaire ou si c\'est une erreur.']];
        }

        $messages = [];
        if ($order->status === 'pending') {
            $next = match (true) {
                $cash => 'Paiement à la livraison : préparez '.gnf($order->total).'.',
                in_array($order->payment_status, ['unpaid', 'partially_paid'], true) => 'Pour la valider, '.$howToPay,
                $order->payment_status === 'paid' => 'Paiement bien reçu, merci !',
                default => 'Nous vérifions votre paiement.',
            };
            $messages['confirm'] = ['Confirmer la commande', $hello."\n".$ref.' : votre commande est confirmée ✅'."\n".$next."\n".$invoice];
        }
        if (! $order->isCashOnDelivery() && in_array($order->payment_status, ['unpaid', 'partially_paid'], true)) {
            $messages['payment'] = ['Rappeler le paiement', $hello."\n".$ref.' : il reste le paiement. Pour recevoir votre commande, '.$howToPay."\n".$invoice];
        }
        if ($order->payment_status === 'paid') {
            $messages['paid'] = ['Paiement reçu', $hello."\n".$ref.' : paiement bien reçu ✅ Merci !'];
        }
        if ($order->status !== 'completed' && $order->fulfillment_status !== 'fulfilled') {
            $messages['ready'] = filled($order->address)
                ? ['Commande en route', $hello."\n".$ref.' : votre commande est en route 🛵 Livraison à : '.$order->address.'.'
                    .($cash ? "\n".'Merci de préparer '.gnf($order->total).'.' : '')]
                : ['Commande prête', $hello."\n".$ref.' : votre commande est prête, vous pouvez passer la récupérer.'
                    .($cash ? "\n".'Prévoyez '.gnf($order->total).'.' : '')];
        }
        if ($order->status === 'completed' || $order->fulfillment_status === 'fulfilled') {
            $messages['thanks'] = ['Remercier le client', $hello."\n".'Merci pour votre achat 🙏 Vos amis peuvent commander ici : '.route('shop.show', $order->store)];
        }
        $messages['invoice'] = ['Envoyer la facture', $hello."\n".$ref.' : voici votre facture.'."\n".route('invoice.show', $order)];

        return $messages;
    }

    /** Lien WhatsApp sans destinataire : le commerçant choisit le contact, le groupe ou son statut. */
    public static function share(string $text): string
    {
        return 'https://wa.me/?text='.rawurlencode($text);
    }

    public static function url(string $phone, string $text): string
    {
        return 'https://wa.me/'.$phone.'?text='.rawurlencode($text);
    }
}
