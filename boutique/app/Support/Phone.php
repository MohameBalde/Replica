<?php

namespace App\Support;

/**
 * Numéros guinéens : tout est stocké en 224XXXXXXXXX (chiffres seuls),
 * le format exigé par les liens wa.me.
 */
class Phone
{
    public const COUNTRY_CODE = '224';

    /**
     * Accepte "620 00 00 00", "+224 620000000", "00224620000000"...
     * Retourne "224620000000", ou null si le numéro n'est pas valable.
     */
    public static function normalize(?string $input): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $input);

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 9) {
            $digits = self::COUNTRY_CODE.$digits;
        }

        // Mobiles guinéens : 9 chiffres commençant par 6 après l'indicatif.
        if (preg_match('/^2246\d{8}$/', $digits)) {
            return $digits;
        }

        return null;
    }

    public static function display(?string $phone): string
    {
        if (! $phone || ! preg_match('/^224(\d{3})(\d{2})(\d{2})(\d{2})$/', $phone, $m)) {
            return (string) $phone;
        }

        return "+224 {$m[1]} {$m[2]} {$m[3]} {$m[4]}";
    }
}
