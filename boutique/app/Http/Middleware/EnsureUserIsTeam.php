<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Espace « Gestion » : réservé à l'équipe Wanllan. Pour les autres, la page n'existe pas. */
class EnsureUserIsTeam
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->is_team, 404);

        return $next($request);
    }
}
