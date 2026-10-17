<?php

namespace App\Support;

/**
 * Les formules Wanllan (hypothèses de prix à tester, voir replica/launch/pricing.md).
 * Une seule source de vérité : la page d'accueil, l'écran « Mon abonnement »
 * et les limites de l'app lisent toutes ce fichier.
 */
class Plans
{
    public const FREE = 'free';

    public const MERCHANT = 'merchant';

    public const PRO = 'pro';

    /** Nombre de mois payés pour un an : 2 mois offerts. */
    public const YEAR_PRICE_MONTHS = 10;

    public static function all(): array
    {
        return [
            self::FREE => [
                'name' => 'Découverte',
                'for' => 'Pour essayer et commencer à vendre',
                'monthly' => 0,
                'max_products' => 15,
                'badge' => true,
                'features' => ['15 produits', 'Commandes illimitées', 'Paiement à la livraison contrôlé', 'Orange Money et MTN MoMo'],
            ],
            self::MERCHANT => [
                'name' => 'Commerçant',
                'for' => 'Pour la boutique qui vend chaque semaine',
                'monthly' => (int) config('wanllan.prices.merchant'),
                'max_products' => null,
                'badge' => true,
                'features' => ['Produits illimités', 'Tout ce qu\'il y a dans Découverte', '« Ce qui fait vendre » : ventes par pub', 'Support WhatsApp'],
            ],
            self::PRO => [
                'name' => 'Pro',
                'for' => 'Pour les grandes boutiques et les grossistes',
                'monthly' => (int) config('wanllan.prices.pro'),
                'max_products' => null,
                'badge' => false,
                'features' => ['Tout ce qu\'il y a dans Commerçant', 'Sans la mention « créée avec Wanllan »', 'Support prioritaire', 'Mise en place de la boutique offerte avec l\'année'],
            ],
        ];
    }

    public static function get(string $plan): array
    {
        return self::all()[$plan] ?? self::all()[self::FREE];
    }

    public static function yearly(string $plan): int
    {
        return self::get($plan)['monthly'] * self::YEAR_PRICE_MONTHS;
    }
}
