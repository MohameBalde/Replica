<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Horaires d'ouverture d'une boutique, à l'heure de Conakry.
 * $days : [1 => ['open' => '08:00', 'close' => '20:00'], …, 7 => null] (1 = lundi, null = fermé ce jour-là).
 * Sans horaires (null), la boutique est toujours ouverte et rien n'est affiché.
 */
class OpeningHours
{
    public const DAYS = [1 => 'Lundi', 2 => 'Mardi', 3 => 'Mercredi', 4 => 'Jeudi', 5 => 'Vendredi', 6 => 'Samedi', 7 => 'Dimanche'];

    public function __construct(private readonly ?array $days) {}

    public function isSet(): bool
    {
        return $this->days !== null;
    }

    /** @return array{open: string, close: string}|null */
    public function slot(int $isoDay): ?array
    {
        return $this->days[$isoDay] ?? null;
    }

    public function isOpenAt(CarbonImmutable $at): bool
    {
        if (! $this->isSet()) {
            return true;
        }
        $at = $at->setTimezone(Period::TZ);
        $slot = $this->slot($at->dayOfWeekIso);
        $time = $at->format('H:i');

        return $slot !== null && $time >= $slot['open'] && $time < $slot['close'];
    }

    /** Prochaine ouverture après $at, dans les 7 jours ; null si aucun jour n'est ouvert. */
    public function nextOpening(CarbonImmutable $at): ?CarbonImmutable
    {
        $at = $at->setTimezone(Period::TZ);
        for ($i = 0; $i <= 7; $i++) {
            $day = $at->startOfDay()->addDays($i);
            if (! $slot = $this->slot($day->dayOfWeekIso)) {
                continue;
            }
            [$h, $m] = array_map('intval', explode(':', $slot['open']));
            if (($open = $day->setTime($h, $m)) > $at) {
                return $open;
            }
        }

        return null;
    }

    /**
     * Pour la vitrine : ['open' => true, 'label' => « Ouvert jusqu'à 20 h »] ou
     * ['open' => false, 'label' => « Fermé · ouvre demain à 8 h », 'next' => « demain à 8 h »]. Null sans horaires.
     */
    public function status(CarbonImmutable $at): ?array
    {
        if (! $this->isSet()) {
            return null;
        }
        $at = $at->setTimezone(Period::TZ);
        if ($this->isOpenAt($at)) {
            return ['open' => true, 'label' => 'Ouvert jusqu\'à '.self::time($this->slot($at->dayOfWeekIso)['close']), 'next' => null];
        }
        if (! $next = $this->nextOpening($at)) {
            return ['open' => false, 'label' => 'Fermé', 'next' => null];
        }
        $when = match (true) {
            $next->isSameDay($at) => 'aujourd\'hui',
            $next->isSameDay($at->addDay()) => 'demain',
            default => mb_strtolower(self::DAYS[$next->dayOfWeekIso]),
        }.' à '.self::time($next->format('H:i'));

        return ['open' => false, 'label' => 'Fermé · ouvre '.$when, 'next' => $when];
    }

    /** La semaine en lignes lisibles : « Lundi : 8 h – 20 h », « Dimanche : fermé ». */
    public function lines(): array
    {
        return collect(self::DAYS)->map(fn ($name, $d) => $name.' : '.(($s = $this->slot($d))
            ? self::time($s['open']).' – '.self::time($s['close'])
            : 'fermé'))->values()->all();
    }

    /** « 8 h », « 8 h 30 », « 20 h », « minuit » (fermeture « 24:00 »). */
    public static function time(string $hm): string
    {
        if ($hm === '24:00') {
            return 'minuit';
        }
        [$h, $m] = array_map('intval', explode(':', $hm));

        return $h.' h'.($m ? ' '.str_pad((string) $m, 2, '0', STR_PAD_LEFT) : '');
    }
}
