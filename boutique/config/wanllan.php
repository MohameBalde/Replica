<?php

return [
    // Prix en GNF : hypothèses à valider avec les premiers commerçants (replica/launch/pricing.md).
    'prices' => [
        'merchant' => (int) env('WANLLAN_PRICE_MERCHANT', 50000),
        'pro' => (int) env('WANLLAN_PRICE_PRO', 100000),
        'setup' => (int) env('WANLLAN_PRICE_SETUP', 100000),
    ],

    // Où le commerçant paie son abonnement et écrit au support.
    'pay_orange_money' => env('WANLLAN_PAY_ORANGE_MONEY', '224620000000'),
    'pay_name' => env('WANLLAN_PAY_NAME', 'Wanllan'),
    'support_whatsapp' => env('WANLLAN_SUPPORT_WHATSAPP', '224620000000'),
    'support_hours' => env('WANLLAN_SUPPORT_HOURS', '8 h – 20 h, réponse en moins de 2 h'),

    // Éditeur du service, affiché dans les pages Conditions et Confidentialité.
    'legal_name' => env('WANLLAN_LEGAL_NAME', 'Mohamed Baldé'),
    'legal_city' => env('WANLLAN_LEGAL_CITY', 'Conakry, Guinée'),
    'contact_email' => env('WANLLAN_CONTACT_EMAIL'),

    // Rappel affiché dans l'espace commerçant avant la fin de l'abonnement.
    'reminder_days' => 3,
];
