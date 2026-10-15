<?php

namespace App\Services;

use App\Models\Order;

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

    public static function url(string $phone, string $text): string
    {
        return 'https://wa.me/'.$phone.'?text='.rawurlencode($text);
    }
}
