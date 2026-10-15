<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Toutes les pages admin travaillent sur la boutique du commerçant connecté.
 * Sans boutique, on l'envoie la créer.
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

        $request->attributes->set('store', $store);
        view()->share('currentStore', $store);

        return $next($request);
    }
}
