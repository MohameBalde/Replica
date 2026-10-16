<?php

return [
    // Proxys dont on accepte les en-têtes X-Forwarded-* (adresse IP du client, HTTPS).
    // Vide par défaut : sur Lightsail, Nginx reçoit les visiteurs directement, et faire
    // confiance à tout le monde laisserait un visiteur choisir son IP pour contourner
    // les limites de tentatives. Derrière un proxy (Cloudflare, répartiteur de charge),
    // mettre ses adresses, séparées par des virgules, ou « * » s'il est le seul accès.
    'proxies' => env('TRUSTED_PROXIES') ?: null,
];
