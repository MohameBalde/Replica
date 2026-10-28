<?php

namespace App\Http\Controllers\Team;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\SubscriptionPayment;
use App\Models\TeamAction;
use App\Services\WhatsAppMessage;
use App\Support\Plans;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Espace « Gestion » de l'équipe Wanllan, depuis un téléphone : les boutiques,
 * les abonnements payés par Orange Money, les suspensions et les relances.
 * Chaque action est tracée dans team_actions.
 */
class StoreController extends Controller
{
    public const FILTERS = ['payantes' => 'Payantes', 'relancer' => 'À relancer', 'suspendues' => 'Suspendues'];

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q'));
        $filter = array_key_exists((string) $request->query('filtre'), self::FILTERS) ? $request->query('filtre') : null;

        $stores = Store::query()
            ->with('user:id,name,phone')
            ->withCount('products')
            ->withCount(['orders' => fn ($q) => $q->where('status', '!=', 'cancelled')])
            ->withMax('orders', 'created_at')
            ->when($search !== '', function ($q) use ($search) {
                $digits = preg_replace('/\D+/', '', $search);
                $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%"))
                    ->when(strlen($digits) >= 3, fn ($w) => $w->orWhere('whatsapp_number', 'like', "%{$digits}%")
                        ->orWhereHas('user', fn ($u) => $u->where('phone', 'like', "%{$digits}%"))));
            })
            ->when($filter === 'payantes', fn ($q) => $q->paid())
            ->when($filter === 'suspendues', fn ($q) => $q->whereNotNull('suspended_at'))
            ->when($filter === 'relancer', fn ($q) => $q->toRemind()->orderBy('plan_expires_at'), fn ($q) => $q->latest('id'))
            ->paginate(30)
            ->withQueryString();

        $monthStart = now('Africa/Conakry')->startOfMonth()->utc();

        return view('team.index', [
            'stores' => $stores,
            'search' => $search,
            'filter' => $filter,
            'stats' => [
                'stores' => Store::count(),
                'new' => Store::where('created_at', '>=', now()->subDays(7))->count(),
                'paid' => Store::paid()->count(),
                'remind' => Store::toRemind()->count(),
                'suspended' => Store::whereNotNull('suspended_at')->count(),
                'month' => (int) SubscriptionPayment::where('created_at', '>=', $monthStart)->sum('amount'),
            ],
            'actions' => TeamAction::with('user:id,name')->latest('created_at')->latest('id')->limit(5)->get(),
        ]);
    }

    public function show(Store $store): View
    {
        $store->load('user');

        return view('team.show', [
            'store' => $store,
            'plans' => Plans::all(),
            'methods' => SubscriptionPayment::METHODS,
            'productCount' => $store->products()->count(),
            'orderCount' => $store->orders()->where('status', '!=', 'cancelled')->count(),
            'lastOrderAt' => $store->orders()->max('created_at'),
            'payments' => $store->subscriptionPayments()->reorder()->orderByDesc('id')->limit(24)->get(),
            'actions' => TeamAction::where('store_id', $store->id)->with('user:id,name')->latest('created_at')->latest('id')->limit(20)->get(),
            'contactUrl' => WhatsAppMessage::url($store->whatsapp_number, WhatsAppMessage::toMerchant($store)),
        ]);
    }

    public function recordPayment(Request $request, Store $store): RedirectResponse
    {
        $digits = preg_replace('/\D+/', '', (string) $request->input('amount'));
        $request->merge([
            'amount' => $digits === '' ? null : $digits, // vide = prix de la formule ; 0 = offert
            'reference' => mb_strtoupper(trim((string) $request->input('reference'))) ?: null,
        ]);

        $data = $request->validate([
            'plan' => ['required', Rule::in(Plans::paid())],
            'months' => ['required', 'integer', 'min:1', 'max:36'],
            'amount' => ['nullable', 'integer', 'min:0'],
            'method' => ['required', Rule::in(array_keys(SubscriptionPayment::METHODS))],
            'reference' => ['nullable', 'string', 'max:60'],
        ], [
            'plan.*' => 'Choisissez la formule payée.',
            'months.*' => 'Indiquez un nombre de mois entre 1 et 36.',
            'method.*' => 'Choisissez le moyen de paiement.',
            'reference.max' => 'Le n° de transaction est trop long.',
        ]);

        $expected = Plans::price($data['plan'], (int) $data['months']);
        $amount = $data['amount'] !== null ? (int) $data['amount'] : $expected;

        if ($amount < $expected && ! $request->boolean('accept_lower')) {
            return back()->withInput()->withErrors(['amount' => 'Montant inférieur au prix ('.gnf($expected).'). Cochez « Montant réduit accepté » pour l\'enregistrer quand même.']);
        }

        // Une capture renvoyée ou un double appui sur un réseau lent ne doit pas payer deux fois.
        // Le verrou sur la boutique fait passer deux envois simultanés l'un après l'autre.
        $payment = DB::transaction(function () use ($store, $data, $amount) {
            Store::whereKey($store->id)->lockForUpdate()->first();

            if ($data['reference'] && $already = SubscriptionPayment::where('reference', $data['reference'])->with('store:id,name')->first()) {
                throw ValidationException::withMessages(['reference' => 'Ce n° de transaction est déjà enregistré ('
                    .($already->store?->name ?? 'boutique supprimée').', le '.$already->created_at->timezone('Africa/Conakry')->format('d/m/Y').').']);
            }

            $sameJustNow = $store->subscriptionPayments()->reorder()->where('created_at', '>=', now()->subMinutes(2))->where([
                'plan' => $data['plan'], 'months' => (int) $data['months'], 'amount' => $amount,
                'method' => $data['method'], 'reference' => $data['reference'],
            ])->exists();
            if ($sameJustNow) {
                throw ValidationException::withMessages(['duplicate' => 'Ce paiement vient d\'être enregistré (double envoi). '
                    .'Si c\'est vraiment un deuxième paiement, renvoyez-le dans 2 minutes.']);
            }

            return $store->recordSubscription($data['plan'], (int) $data['months'], $amount, $data['method'], $data['reference']);
        });
        $end = $payment->period_end->timezone('Africa/Conakry')->format('d/m/Y');
        $planName = Plans::get($data['plan'])['name'];

        TeamAction::record($request->user(), $store, 'paiement', $planName.' · '.$payment->months.' mois · '.gnf($amount)
            .' · '.SubscriptionPayment::METHODS[$data['method']].($data['reference'] ? ' · réf. '.$data['reference'] : '').' → '.$end);

        return redirect()->route('team.stores.show', $store->id)
            ->with('status', 'Formule '.$planName.' active jusqu\'au '.$end.'.')
            ->with('confirm_url', WhatsAppMessage::url($store->whatsapp_number, WhatsAppMessage::subscriptionConfirmed($store, $payment)));
    }

    public function cancelPayment(Request $request, Store $store): RedirectResponse
    {
        $payment = $store->cancelLatestSubscription();
        if (! $payment) {
            return back()->withErrors(['payment' => 'Aucun paiement à annuler.']);
        }

        TeamAction::record($request->user(), $store, 'paiement_annule', Plans::get($payment->plan)['name'].' · '.$payment->months.' mois · '
            .gnf($payment->amount).($payment->reference ? ' · réf. '.$payment->reference : ''));

        $store->refresh();
        $now = Plans::get($store->activePlan())['name'];

        return redirect()->route('team.stores.show', $store->id)->with('status', 'Dernier paiement annulé. Formule actuelle : '.$now
            .($store->activePlan() !== Plans::FREE ? ' jusqu\'au '.$store->plan_expires_at->timezone('Africa/Conakry')->format('d/m/Y') : '').'.');
    }

    public function suspend(Request $request, Store $store): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.*' => 'Indiquez le motif de la suspension.']);

        if (! $store->isSuspended()) {
            $store->suspend(trim($data['reason']));
            TeamAction::record($request->user(), $store, 'suspension', trim($data['reason']));
        }

        return redirect()->route('team.stores.show', $store->id)
            ->with('status', $store->name.' est suspendue : ses clients voient « Boutique momentanément indisponible ».');
    }

    /**
     * Mot de passe oublié : un lien valable 1 heure, à envoyer sur le WhatsApp du numéro du compte
     * (jamais à un autre numéro : c'est ce qui prouve que la demande vient du propriétaire).
     */
    public function passwordLink(Request $request, Store $store): RedirectResponse
    {
        $owner = $store->user;
        if (! $owner) {
            return back()->withErrors(['password_link' => 'Cette boutique n\'a plus de compte.']);
        }

        $url = $owner->createPasswordResetUrl();
        TeamAction::record($request->user(), $store, 'mot_de_passe', 'Lien envoyé au '.phone_display($owner->phone).', valable 1 h');

        return redirect()->route('team.stores.show', $store->id)
            ->with('status', 'Lien créé. Envoyez-le sur WhatsApp au numéro du compte ; il est valable 1 heure.')
            ->with('password_url', WhatsAppMessage::url($owner->phone, WhatsAppMessage::passwordReset($owner, $url)));
    }

    public function reactivate(Request $request, Store $store): RedirectResponse
    {
        if ($store->isSuspended()) {
            $store->reactivate();
            TeamAction::record($request->user(), $store, 'reactivation');
        }

        return redirect()->route('team.stores.show', $store->id)->with('status', $store->name.' est de nouveau en ligne.');
    }
}
