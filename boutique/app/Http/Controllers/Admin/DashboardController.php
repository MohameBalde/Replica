<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Store;
use App\Services\WhatsAppMessage;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Accueil du commerçant : ce qu'il y a à faire maintenant, puis combien il a
 * vendu. Chaque nombre « à faire » ouvre la liste qui affiche le même nombre.
 * Au plus 7 requêtes agrégées, toutes limitées à la boutique, sans JavaScript.
 */
class DashboardController extends Controller
{
    /** Initiales des jours, indexées par dayOfWeek (0 = dimanche), sans dépendre de la langue de l'app. */
    public const DAY_INITIALS = ['D', 'L', 'M', 'M', 'J', 'V', 'S'];

    public const PENDING_OLD_HOURS = 48;

    public function show(Request $request): View
    {
        /** @var Store $store */
        $store = $request->attributes->get('store');

        $todo = $this->todo($store);
        $starting = ! $todo['live_order'];
        $days = $starting ? Period::DEFAULT_DAYS : Period::days($request->query('periode'));
        $from = Period::start($days);

        $visits = $this->visits($store, $from);
        $mobileMoneyReady = $store->paymentMethods()->get(['id', 'type', 'is_active', 'account_number'])
            ->contains(fn (PaymentMethod $m) => in_array($m->type, [PaymentMethod::ORANGE_MONEY, PaymentMethod::MTN_MOMO], true)
                && $m->is_active && filled($m->account_number));

        $data = [
            'store' => $store,
            'starting' => $starting,
            'todo' => $todo,
            'lowStock' => $this->lowStock($store),
            'mobileMoneyReady' => $mobileMoneyReady,
            'visitsRecent' => $visits['recent'],
            'days' => $days,
            'periodKey' => Period::key($days),
            'from' => $from,
        ];

        if ($starting) {
            return view('admin.dashboard', $data + ['checklist' => $this->checklist($store, $todo, $visits, $mobileMoneyReady)]);
        }

        $sales = $this->sales($store, $from, $days);

        return view('admin.dashboard', $data + [
            'sales' => $sales,
            'topProducts' => $sales['orders'] > 0 ? $this->topProducts($store, $from) : collect(),
            'recentOrders' => $store->orders()->orderByDesc('id')->limit(3)->get([
                'id', 'number', 'customer_name', 'total', 'status', 'payment_status', 'payment_method_type', 'source', 'whatsapp_sent_at', 'created_at',
            ]),
        ]);
    }

    /**
     * Q1 : l'état de toutes les commandes, en une requête. Mêmes règles que les
     * filtres de la liste Commandes (les annulées n'y sont pas à traiter).
     */
    private function todo(Store $store): array
    {
        $todo = [
            'live_order' => false, 'to_verify' => 0, 'pending' => 0, 'pending_old' => 0,
            'pending_no_wa' => 0, 'cod_count' => 0, 'cod_amount' => 0, 'cod_completed' => 0,
        ];
        // Aucune commande n'a jamais été passée : rien à lire.
        if ($store->next_order_number <= 1001) {
            return $todo;
        }

        $rows = $store->orders()->toBase()
            ->selectRaw('status, payment_status, payment_method_type, COUNT(*) AS n, SUM(total) AS amount, '
                .'SUM(CASE WHEN created_at < ? THEN 1 ELSE 0 END) AS old, '
                .'SUM(CASE WHEN whatsapp_sent_at IS NULL THEN 1 ELSE 0 END) AS no_wa',
                [now()->subHours(self::PENDING_OLD_HOURS)->utc()->toDateTimeString()])
            ->groupBy('status', 'payment_status', 'payment_method_type')
            ->get();

        foreach ($rows as $r) {
            $n = (int) $r->n;
            $active = $r->status !== 'cancelled';
            $todo['live_order'] = $todo['live_order'] || ($active && $n > 0);
            if ($active && $r->payment_status === 'confirming') {
                $todo['to_verify'] += $n;
            }
            if ($r->status === 'pending') {
                $todo['pending'] += $n;
                $todo['pending_old'] += (int) $r->old;
                $todo['pending_no_wa'] += (int) $r->no_wa;
            }
            if ($active && $r->payment_method_type === PaymentMethod::CASH_ON_DELIVERY && $r->payment_status === 'unpaid') {
                $todo['cod_count'] += $n;
                $todo['cod_amount'] += (int) $r->amount;
                if ($r->status === 'completed') {
                    $todo['cod_completed'] += $n;
                }
            }
        }

        return $todo;
    }

    /** Q3 : visites par source, toutes dates et sur la période. */
    private function visits(Store $store, CarbonImmutable $from): array
    {
        $rows = $store->dailyVisits()->toBase()
            ->selectRaw('source, SUM(visits) AS total, SUM(CASE WHEN day >= ? THEN visits ELSE 0 END) AS recent', [$from->toDateString()])
            ->groupBy('source')
            ->get();

        return [
            'recent' => (int) $rows->sum(fn ($r) => (int) $r->recent),
            // Les visites « direct » comptent aussi les clics du commerçant sur « Voir ma boutique ».
            'shared' => (int) $rows->where('source', '!=', 'direct')->sum(fn ($r) => (int) $r->total),
        ];
    }

    /** Q4 : variantes suivies d'un produit visible, à LOW_STOCK ou moins. 6 lignes pour savoir s'il y en a d'autres. */
    private function lowStock(Store $store): Collection
    {
        return DB::table('variants')
            ->join('products', 'products.id', '=', 'variants.product_id')
            ->where('products.store_id', $store->id)
            ->where('products.is_visible', true)
            ->where('products.track_stock', true)
            ->whereNotNull('variants.stock')
            ->where('variants.stock', '<=', Product::LOW_STOCK)
            ->orderBy('variants.stock')->orderBy('products.name')->orderBy('variants.position')
            ->limit(6)
            ->get(['products.id as product_id', 'products.name as product_name', 'variants.name as variant_name', 'variants.stock']);
    }

    /** Les 5 étapes de « Bien démarrer », cochées d'après les vraies données (Q5). */
    private function checklist(Store $store, array $todo, array $visits, bool $mobileMoneyReady): array
    {
        $products = $store->products()->toBase()
            ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN is_visible = ? THEN 1 ELSE 0 END) AS visible', [true])
            ->first();
        $productDone = (int) $products->visible > 0;
        // Seules les visites venues d'un lien partagé comptent : une commande d'essai du
        // commerçant (directe, puis annulée) ne coche pas l'étape et garde le bouton Partager.
        $shareDone = $visits['shared'] > 0;

        $text = $store->welcomeMessageText();
        if (! str_contains($text, route('shop.show', $store))) {
            $text = trim($text."\n".route('shop.show', $store).'?src=whatsapp');
        }

        $steps = [
            'store' => true,
            'product' => $productDone,
            'payment' => $mobileMoneyReady,
            'share' => $shareDone,
            'order' => false,
        ];

        return [
            'steps' => $steps,
            'done' => count(array_filter($steps)),
            'next' => collect($steps)->search(false),
            'hiddenProducts' => (int) $products->total > 0 && ! $productDone,
            'shareUrl' => WhatsAppMessage::share($text),
        ];
    }

    /**
     * Q2 : commandes comptées de la période, par jour, statut de paiement et source.
     * DATE(created_at) donne le jour UTC, qui est aussi le jour de Conakry (UTC+0,
     * sans heure d'été). Si le fuseau changeait, il faudrait regrouper en PHP.
     */
    private function sales(Store $store, CarbonImmutable $from, int $days): array
    {
        $rows = $store->orders()->counted()->where('created_at', '>=', $from->utc())->toBase()
            ->selectRaw('DATE(created_at) AS d, payment_status, source, COUNT(*) AS n, SUM(total) AS amount')
            ->groupByRaw('DATE(created_at), payment_status, source')
            ->get();

        $byDay = [];
        for ($i = 0; $i < $days; $i++) {
            $byDay[$from->addDays($i)->toDateString()] = ['n' => 0, 'amount' => 0];
        }
        $sources = [];
        $paid = $due = $partial = $orders = 0;
        foreach ($rows as $r) {
            $n = (int) $r->n;
            $amount = (int) $r->amount;
            $orders += $n;
            if ($r->payment_status === 'paid') {
                $paid += $amount;
            } else {
                $due += $amount; // non payée, à vérifier ou payée en partie (comptée en entier : aucun montant reçu n'est enregistré)
            }
            if ($r->payment_status === 'partially_paid') {
                $partial += $n;
            }
            $day = substr((string) $r->d, 0, 10);
            if (isset($byDay[$day])) {
                $byDay[$day]['n'] += $n;
                $byDay[$day]['amount'] += $amount;
            }
            $sources[$r->source] = ($sources[$r->source] ?? 0) + $n;
        }

        // Réseaux qui ont apporté le plus de commandes, tous nommés en cas d'égalité.
        // « Direct » et « saisie à la main » ne sont pas des réseaux : si direct est seul en tête,
        // la page conseille les liens suivis.
        $networks = array_diff_key($sources, ['direct' => 0, Order::MANUAL => 0]);
        $topCount = $networks ? max($networks) : 0;
        $topSources = $topCount > 0 && $topCount >= max($sources['direct'] ?? 0, $sources[Order::MANUAL] ?? 0)
            ? array_values(array_filter(array_keys(Order::SOURCES), fn ($s) => ($networks[$s] ?? 0) === $topCount))
            : [];

        $max = max(1, max(array_column($byDay, 'n')));
        $series = collect($byDay)->map(fn ($v, $day) => $v + [
            'day' => $day,
            'label' => $day === Period::today() ? 'Auj.' : self::DAY_INITIALS[CarbonImmutable::parse($day)->dayOfWeek],
            'height' => $v['n'] > 0 ? max(4, (int) round($v['n'] / $max * 100)) : 1,
        ])->values();

        return [
            'orders' => $orders,
            'paid' => $paid,
            'due' => $due,
            'partial' => $partial,
            'total' => $paid + $due,
            'series' => $series,
            'topSources' => $topSources,
            'topSourceCount' => $topCount,
        ];
    }

    /**
     * Q7 : les 3 produits les plus commandés de la période (en quantités). Un produit
     * retiré garde son nom de commande ; GROUP BY sur des colonnes sélectionnées
     * seulement (ONLY_FULL_GROUP_BY de MySQL).
     */
    private function topProducts(Store $store, CarbonImmutable $from): Collection
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.store_id', $store->id)
            ->whereIn('orders.status', Order::COUNTED_STATUSES)
            ->whereIn('orders.payment_status', Order::COUNTED_PAYMENTS)
            ->where('orders.created_at', '>=', $from->utc())
            ->selectRaw('order_items.product_id, MAX(COALESCE(products.name, order_items.product_name)) AS name, SUM(order_items.quantity) AS qty')
            ->groupByRaw('order_items.product_id, CASE WHEN order_items.product_id IS NULL THEN order_items.product_name END')
            ->orderByDesc('qty')->orderBy('name')
            ->limit(3)
            ->get();
    }
}
