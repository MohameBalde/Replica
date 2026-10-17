<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentProof;
use App\Services\ImageProcessor;
use App\Services\WhatsAppMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvoiceController extends Controller
{
    public const MAX_PROOFS = 3;

    /** S05 + S07 : facture, instructions de paiement, preuve, bouton WhatsApp. */
    public function show(Order $order): View
    {
        $order->load('items', 'store', 'paymentMethod')->loadCount('paymentProofs');

        return view('shop.invoice', [
            'order' => $order,
            'store' => $order->store,
        ]);
    }

    /** S05 : le client envoie la capture de son paiement mobile money. */
    public function storeProof(Request $request, Order $order, ImageProcessor $images): RedirectResponse
    {
        abort_if($order->isCancelled() || $order->isCashOnDelivery(), 403);

        if ($order->paymentProofs()->count() >= self::MAX_PROOFS) {
            return back()->withErrors(['proof' => 'Vous avez déjà envoyé plusieurs captures. Contactez la boutique sur WhatsApp.']);
        }

        $data = $request->validate([
            'proof' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'reference' => ['nullable', 'string', 'max:60'],
        ], [
            'proof.required' => 'Ajoutez la capture de votre paiement.',
            'proof.image' => 'Ce fichier n\'est pas une image.',
            'proof.max' => 'Image trop lourde (8 Mo maximum).',
        ]);

        $path = $images->storeSingle($request->file('proof'), 'proofs/'.$order->store_id, PaymentProof::DISK, 1600);
        $order->paymentProofs()->create(['path' => $path, 'reference' => $data['reference'] ?? null]);

        if ($order->payment_status === 'unpaid') {
            $order->update(['payment_status' => 'confirming']);
        }

        return back()->with('status', 'Capture reçue. La boutique va vérifier votre paiement.');
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
