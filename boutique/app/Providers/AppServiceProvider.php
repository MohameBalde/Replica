<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Un compteur par usage : ajouter 10 articles au panier ne doit pas
        // empêcher d'envoyer sa capture de paiement ensuite.
        RateLimiter::for('cart', fn (Request $r) => Limit::perMinute(60)->by('cart|'.$r->ip()));
        RateLimiter::for('checkout', fn (Request $r) => Limit::perMinute(10)->by('checkout|'.$r->ip()));
        // Le limiteur passe avant la liaison des modèles : on s'appuie sur le chemin (/c/{jeton}/preuve).
        RateLimiter::for('proof', fn (Request $r) => Limit::perHour(10)->by('proof|'.$r->ip().'|'.$r->path()));
        RateLimiter::for('auth', fn (Request $r) => Limit::perMinute(10)->by('auth|'.$r->ip()));
    }
}
