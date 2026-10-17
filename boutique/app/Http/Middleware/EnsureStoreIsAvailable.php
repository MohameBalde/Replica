<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Boutique suspendue par l'équipe Wanllan : la vitrine, le panier et la commande
 * affichent « momentanément indisponible ». Les factures déjà envoyées restent
 * lisibles (routes /c/…), pour les clients qui ont déjà commandé.
 */
class EnsureStoreIsAvailable
{
    public function handle(Request $request, Closure $next): Response
    {
        $store = $request->route('store');

        if ($store instanceof Store && $store->isSuspended()) {
            return response()->view('shop.unavailable', ['store' => $store], 403);
        }

        return $next($request);
    }
}
