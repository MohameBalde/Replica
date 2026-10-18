<?php

namespace App\Services;

use App\Models\DailyVisit;
use App\Models\Order;
use App\Models\Store;
use App\Support\Period;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * D'où vient le client : ?src=facebook / tiktok / whatsapp...
 * La source est gardée en session à l'arrivée, puis copiée sur la commande.
 */
class SourceTracker
{
    public static function capture(Request $request, Store $store): void
    {
        $key = 'src.'.$store->id;
        $src = $request->query('src');

        if (is_string($src)) {
            $src = strtolower($src);
            $src = array_key_exists($src, Order::SOURCES) && $src !== Order::MANUAL ? $src : 'direct';
            $campaign = $request->query('c');
            session([$key => [
                'source' => $src,
                'campaign' => is_string($campaign) ? mb_substr(preg_replace('/[^\w\-]/u', '', $campaign), 0, 60) : null,
            ]]);
        }

        // Une visite comptée par boutique et par session.
        $seenKey = 'visited.'.$store->id;
        if (! session($seenKey)) {
            session([$seenKey => true]);
            self::countVisit($store, self::current($store)['source']);
        }
    }

    /** @return array{source:string, campaign:?string} */
    public static function current(Store $store): array
    {
        return session('src.'.$store->id, ['source' => 'direct', 'campaign' => null]);
    }

    private static function countVisit(Store $store, string $source): void
    {
        $day = Period::today();
        $visit = DailyVisit::firstOrCreate(['store_id' => $store->id, 'day' => $day, 'source' => $source]);
        DailyVisit::whereKey($visit->id)->update(['visits' => DB::raw('visits + 1')]);
    }
}
