<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\WhatsAppMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    /** S05 + S07 : facture, instructions de paiement, bouton WhatsApp. */
    public function show(Order $order): View
    {
        $order->load('items', 'store', 'paymentMethod');

        return view('shop.invoice', [
            'order' => $order,
            'store' => $order->store,
        ]);
    }

    /** S06 : note l'envoi et ouvre WhatsApp avec le message prérempli. */
    public function whatsapp(Order $order): RedirectResponse
    {
        $order->load('store');

        if (! $order->whatsapp_sent_at) {
            $order->forceFill(['whatsapp_sent_at' => now()])->save();
        }

        return redirect()->away(WhatsAppMessage::url($order->store->whatsapp_number, WhatsAppMessage::forOrder($order)));
    }
}
