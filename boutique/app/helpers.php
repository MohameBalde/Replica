<?php

if (! function_exists('gnf')) {
    /**
     * Formate un montant en francs guinéens : 150000 -> "150 000 GNF".
     * Le GNF n'a pas de centimes : on stocke et on affiche des entiers.
     */
    function gnf(int|float|null $amount, bool $withCurrency = true): string
    {
        $formatted = number_format((int) $amount, 0, ',', ' ');

        return $withCurrency ? $formatted.' GNF' : $formatted;
    }
}

if (! function_exists('phone_display')) {
    /** 224620000000 -> "+224 620 00 00 00" pour l'affichage. */
    function phone_display(?string $phone): string
    {
        return App\Support\Phone::display($phone);
    }
}
