<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\WhatsAppMessage;
use App\Support\Plans;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * S23 : « Mon abonnement ». Prépayé, sans carte, sans prélèvement :
 * le commerçant voit sa formule, sa date de fin, et comment payer.
 */
class SubscriptionController extends Controller
{
    public function show(Request $request): View
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');
        $message = 'Bonjour Wanllan, je veux payer l\'abonnement pour ma boutique « '.$store->name.' » ('.route('shop.show', $store).').';

        return view('admin.subscription', [
            'store' => $store,
            'plans' => Plans::all(),
            'current' => $store->activePlan(),
            'productCount' => $store->products()->count(),
            'payments' => $store->subscriptionPayments()->limit(12)->get(),
            'supportUrl' => WhatsAppMessage::url(config('wanllan.support_whatsapp'), $message),
        ]);
    }
}
