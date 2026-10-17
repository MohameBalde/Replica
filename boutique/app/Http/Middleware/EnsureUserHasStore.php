<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Toutes les pages admin travaillent sur la boutique du commerçant connecté.
 * Sans boutique, on l'envoie la créer. Boutique suspendue : un seul écran,
 * qui explique pourquoi et comment joindre le support.
 */
class EnsureUserHasStore
{
    public function handle(Request $request, Closure $next): Response
    {
        // Requête fraîche, pas la relation éventuellement mise en cache sur l'utilisateur.
        $store = $request->user()?->store()->first();

        if (! $store) {
            return redirect()->route('admin.store.create');
        }

        if ($store->isSuspended()) {
            return response()->view('admin.suspended', ['store' => $store], 403);
        }

        $request->attributes->set('store', $store);
        view()->share('currentStore', $store);

        return $next($request);
    }
}
