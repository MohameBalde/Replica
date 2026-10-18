<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Périodes des chiffres du commerçant, en jours de Conakry : « 7 jours » =
 * aujourd'hui et les 6 jours d'avant, à partir de minuit heure de Conakry.
 */
class Period
{
    public const TZ = 'Africa/Conakry';

    /** Valeur de ?periode= => nombre de jours. */
    public const CHOICES = ['aujourdhui' => 1, '7j' => 7, '30j' => 30];

    public const DEFAULT_DAYS = 7;

    public static function days(?string $choice): int
    {
        return self::CHOICES[$choice] ?? self::DEFAULT_DAYS;
    }

    public static function key(int $days): string
    {
        return array_search($days, self::CHOICES, true) ?: '7j';
    }

    /** Premier instant de la période, minuit à Conakry. Passer ->utc() avant de comparer à created_at. */
    public static function start(int $days): CarbonImmutable
    {
        return CarbonImmutable::now(self::TZ)->startOfDay()->subDays($days - 1);
    }

    public static function today(): string
    {
        return CarbonImmutable::now(self::TZ)->toDateString();
    }
}
