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

    /**
     * Taille maximale du message une fois encodé dans le lien. Le lien part dans l'en-tête
     * Location de la redirection, et nginx refuse (502) une réponse dont les en-têtes
     * dépassent 4 Ko : avec les cookies, 2 000 octets laissent de la marge.
     */
    public const MAX_ENCODED = 2000;

    /** Le message complet s'il tient ; sinon moins d'articles listés, puis réponses et adresse raccourcies. */
    public static function forOrder(Order $order): string
    {
        $order->loadMissing('items', 'store');

        for ($listed = min(self::MAX_ITEMS_LISTED, $order->items->count()); $listed >= 1; $listed--) {
            $text = self::orderText($order, $listed, false);
            if (strlen(rawurlencode($text)) <= self::MAX_ENCODED) {
                return $text;
            }
        }
        $text = self::orderText($order, 1, true);
        if (strlen(rawurlencode($text)) <= self::MAX_ENCODED) {
            return $text;
        }

        // Dernier recours : le début du message, puis le lien de la facture, qui a tout le détail.
        $tail = "\n…\n\nDétail : ".route('invoice.show', $order);
        while ($text !== '' && strlen(rawurlencode($text.$tail)) > self::MAX_ENCODED) {
            $text = mb_substr($text, 0, (int) floor(mb_strlen($text) * 0.9));
        }

        return $text.$tail;
    }

    private static function orderText(Order $order, int $listed, bool $short): string
    {
        $lines = ['Bonjour '.$order->store->name.' 👋', 'Voici ma commande *#'.$order->number.'* :', ''];

        foreach ($order->items->take($listed) as $item) {
            $lines[] = '• '.$item->quantity.' × '.$item->label()
                .($item->options ? ' + '.Str::limit($item->optionsLabel(), 120, '…') : '')
                .' — '.gnf($item->line_total);
        }
        if ($order->items->count() > $listed) {
            $lines[] = '• … et '.($order->items->count() - $listed).' autre(s) article(s)';
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
            $lines[] = 'Adresse : '.($short ? Str::limit($order->address, 80, '…') : $order->address);
        }
        foreach ($order->answers ?? [] as $qa) {
            $lines[] = $short ? Str::limit($qa['q'], 40, '…').' : '.Str::limit($qa['a'], 60, '…') : $qa['q'].' : '.$qa['a'];
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
        // Nom complet (« El Hadj Oumar Bah » ne devient pas « Bonjour El ») ; espaces et retours à la ligne regroupés.
        $hello = 'Bonjour '.Str::squish($order->customer_name).', ici '.$order->store->name.' 👋';
        $ref = 'Commande #'.$order->number.' ('.gnf($order->total).')';
        $invoice = 'Détail : '.route('invoice.show', $order);
        $partial = $order->payment_status === 'partially_paid';
        $due = $partial ? 'le reste du montant' : gnf($order->total); // le montant déjà reçu n'est pas enregistré
        $delivered = $order->status === 'completed' || $order->fulfillment_status === 'fulfilled';
        $method = $order->paymentMethod;
        $howToPay = 'envoyez '.$due.' par '.$order->paymentLabel()
            .($method && filled($method->account_number) ? ' au '.phone_display($method->account_number).($method->account_name ? ' ('.$method->account_name.')' : '') : '')
            // La page de la commande n'accepte une capture que tant que rien n'est payé.
            .($partial ? ', puis envoyez-nous la capture ici.' : ', puis ajoutez la capture sur le lien ci-dessous.');
        $cashLeft = $order->isCashOnDelivery() && in_array($order->payment_status, ['unpaid', 'partially_paid'], true);

        if ($order->isCancelled()) {
            return ['cancelled' => ['Prévenir de l\'annulation', $hello."\n".$ref.' : votre commande a été annulée. '
                .'Écrivez-nous si vous voulez la refaire ou si c\'est une erreur.']];
        }

        $messages = [];
        if ($order->status === 'pending') {
            $text = match (true) {
                $cashLeft => 'votre commande est confirmée ✅'."\n".'Paiement à la livraison : préparez '.$due.'.',
                $order->payment_status === 'paid' => 'votre commande est confirmée ✅'."\n".'Paiement bien reçu, merci !',
                $order->payment_status === 'confirming' => 'votre commande est bien reçue 👍'."\n".'Nous vérifions votre paiement et revenons vers vous.',
                in_array($order->payment_status, ['unpaid', 'partially_paid'], true) => 'votre commande est confirmée ✅'."\n".'Il reste le paiement : '.$howToPay,
                default => 'votre commande est confirmée ✅',
            };
            $messages['confirm'] = ['Confirmer la commande', $hello."\n".$ref.' : '.$text."\n".$invoice];
        }
        if (! $order->isCashOnDelivery() && in_array($order->payment_status, ['unpaid', 'partially_paid'], true)) {
            $messages['payment'] = ['Rappeler le paiement', $hello."\n".$ref.' : il reste le paiement. '
                .($delivered ? 'Merci de régler : ' : 'Pour recevoir votre commande, ').$howToPay."\n".$invoice];
        }
        if ($order->payment_status === 'paid') {
            $messages['paid'] = ['Paiement reçu', $hello."\n".$ref.' : paiement bien reçu ✅ Merci !'];
        }
        if (! $delivered) {
            $messages['ready'] = filled($order->address)
                ? ['Commande en route', $hello."\n".$ref.' : votre commande est en route 🛵 Livraison à : '.Str::squish($order->address).'.'
                    .($cashLeft ? "\n".'Merci de préparer '.$due.'.' : '')]
                : ['Commande prête', $hello."\n".$ref.' : votre commande est prête, vous pouvez passer la récupérer.'
                    .($cashLeft ? "\n".'Prévoyez '.$due.'.' : '')];
        }
        if ($delivered) {
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
