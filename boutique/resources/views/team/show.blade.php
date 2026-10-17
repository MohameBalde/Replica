@php
    $tz = 'Africa/Conakry';
    $current = $store->activePlan();
@endphp
<x-layouts.admin :title="$store->name" :back="route('team.dashboard')">
    @if (session('confirm_url'))
        <div class="px-4 pt-3">
            <a href="{{ session('confirm_url') }}" class="btn btn-whatsapp" target="_blank" rel="noopener">
                <x-icon name="message" :size="20" /> Envoyer la confirmation
            </a>
        </div>
    @endif

    <section class="section">
        <div class="flex flex-wrap gap-1.5">@include('team._status')</div>
        <p class="mt-2">{{ $store->user?->name ?? 'Compte supprimé' }} · {{ $store->city }}</p>
        <p class="text-sm text-text-muted">WhatsApp des commandes : {{ phone_display($store->whatsapp_number) }}</p>
        @if ($store->user && $store->user->phone !== $store->whatsapp_number)
            <p class="text-sm text-text-muted">Numéro du compte : {{ phone_display($store->user->phone) }}</p>
        @endif
        <p class="text-sm text-text-muted">Créée le {{ $store->created_at->timezone($tz)->format('d/m/Y') }}</p>
        <a href="{{ route('shop.show', $store) }}" class="mt-1 inline-flex items-center gap-1 text-sm font-semibold text-accent" target="_blank" rel="noopener">
            {{ str_replace(['https://', 'http://'], '', route('shop.show', $store)) }} <x-icon name="external" :size="14" />
        </a>

        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-md bg-surface p-2"><p class="text-lg font-bold">{{ $productCount }}</p><p class="text-xs text-text-muted">produits</p></div>
            <div class="rounded-md bg-surface p-2"><p class="text-lg font-bold">{{ $orderCount }}</p><p class="text-xs text-text-muted">commandes</p></div>
            <div class="rounded-md bg-surface p-2">
                <p class="text-sm font-bold leading-7">{{ $lastOrderAt ? \Illuminate\Support\Carbon::parse($lastOrderAt)->timezone($tz)->format('d/m/Y') : '—' }}</p>
                <p class="text-xs text-text-muted">dernière commande</p>
            </div>
        </div>

        <a href="{{ $contactUrl }}" class="btn btn-whatsapp mt-4" target="_blank" rel="noopener">
            <x-icon name="message" :size="20" /> {{ $store->needsReminder() ? 'Relancer sur WhatsApp' : 'Écrire sur WhatsApp' }}
        </a>
    </section>

    <section class="section">
        <h2 class="section-title">Enregistrer un paiement</h2>
        <p class="mb-3 text-sm text-text-muted">
            Formule actuelle : <strong class="text-text">{{ $plans[$current]['name'] }}</strong>@if ($current !== \App\Support\Plans::FREE), jusqu'au {{ $store->plan_expires_at->timezone($tz)->format('d/m/Y') }}.
            Pour la même formule, les mois s'ajoutent à la suite.@else.@endif
        </p>
        <form method="POST" action="{{ route('team.stores.payments', $store->id) }}" novalidate>
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div class="field">
                    <label class="label" for="plan">Formule</label>
                    <select class="input" id="plan" name="plan">
                        @foreach (\App\Support\Plans::paid() as $key)
                            <option value="{{ $key }}" @selected(old('plan', $current !== \App\Support\Plans::FREE ? $current : \App\Support\Plans::MERCHANT) === $key)>{{ $plans[$key]['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="months">Durée</label>
                    <select class="input" id="months" name="months">
                        @foreach ([1, 2, 3, 6, 12, 24] as $m)
                            <option value="{{ $m }}" @selected((int) old('months', 1) === $m)>{{ $m }} mois{{ $m === 12 ? ' (1 an)' : ($m === 24 ? ' (2 ans)' : '') }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p class="help -mt-2 mb-3">
                @foreach (\App\Support\Plans::paid() as $key)
                    {{ $plans[$key]['name'] }} : {{ gnf($plans[$key]['monthly']) }} / mois, {{ gnf(\App\Support\Plans::yearly($key)) }} / an.
                @endforeach
            </p>

            <div class="field">
                <label class="label" for="amount">Montant reçu</label>
                <div class="input-group @error('amount') is-invalid @enderror">
                    <input class="input" id="amount" name="amount" inputmode="numeric" value="{{ old('amount') }}" placeholder="Vide = prix de la formule">
                    <span class="input-addon">GNF</span>
                </div>
                @error('amount')<p class="error">{{ $message }}</p>@enderror
                <label class="mt-2 flex items-center gap-2 text-sm">
                    <input type="checkbox" name="accept_lower" value="1" @checked(old('accept_lower'))> Montant réduit accepté (remise, geste commercial)
                </label>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="field">
                    <label class="label" for="method">Payé par</label>
                    <select class="input" id="method" name="method">
                        @foreach ($methods as $key => $label)<option value="{{ $key }}" @selected(old('method', 'orange_money') === $key)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="label" for="reference">N° de transaction</label>
                    <input class="input @error('reference') is-invalid @enderror" id="reference" name="reference" value="{{ old('reference') }}" maxlength="60" autocapitalize="characters" placeholder="Sur la capture">
                </div>
            </div>
            @error('reference')<p class="error -mt-2 mb-3">{{ $message }}</p>@enderror

            <button class="btn btn-primary btn-lg" type="submit">Enregistrer le paiement</button>
        </form>
    </section>

    @if ($payments->isNotEmpty())
        <section class="section">
            <h2 class="section-title">Paiements</h2>
            <ul class="divide-y divide-border text-sm">
                @foreach ($payments as $pay)
                    <li class="flex justify-between gap-3 py-2">
                        <span>{{ $plans[$pay->plan]['name'] ?? $pay->plan }} · {{ $pay->months }} mois · {{ $methods[$pay->method] ?? $pay->method }}<br>
                            <span class="text-text-muted">du {{ $pay->period_start->timezone($tz)->format('d/m/Y') }} au {{ $pay->period_end->timezone($tz)->format('d/m/Y') }}@if ($pay->reference) · {{ $pay->reference }}@endif</span></span>
                        <span class="price">{{ gnf($pay->amount) }}</span>
                    </li>
                @endforeach
            </ul>
            {{-- Fermé par défaut : on n'annule pas un paiement par erreur. --}}
            <details class="mt-3 rounded-md border border-border p-3">
                <summary class="cursor-pointer text-sm font-semibold">Annuler le dernier paiement (erreur de saisie)</summary>
                <form method="POST" action="{{ route('team.stores.payments.cancel', $store->id) }}" class="mt-3">
                    @csrf @method('DELETE')
                    <p class="text-sm">Le paiement le plus récent est effacé et la date de fin revient à celle d'avant.</p>
                    <button class="btn btn-secondary mt-3" type="submit">Annuler {{ gnf($payments->first()->amount) }} du {{ $payments->first()->created_at->timezone($tz)->format('d/m/Y') }}</button>
                </form>
            </details>
            @error('payment')<p class="error mt-2">{{ $message }}</p>@enderror
        </section>
    @endif

    <section class="section">
        <h2 class="section-title">Suspension</h2>
        @if ($store->isSuspended())
            <p class="rounded-md bg-danger-soft p-3 text-sm text-on-danger-soft">
                Suspendue le {{ $store->suspended_at->timezone($tz)->format('d/m/Y à H:i') }} : {{ $store->suspension_reason }}.
                Ses clients voient « Boutique momentanément indisponible » ; le commerçant voit le motif et le contact du support.
            </p>
            <form method="POST" action="{{ route('team.stores.reactivate', $store->id) }}" class="mt-3">
                @csrf
                <button class="btn btn-primary" type="submit">Réactiver la boutique</button>
            </form>
        @else
            <p class="text-sm text-text-muted">En cas d'arnaque ou de plainte grave. Rien n'est effacé : produits, commandes et clients restent, et la réactivation est immédiate.</p>
            <details class="mt-3 rounded-md border border-danger p-3" @if ($errors->has('reason')) open @endif>
                <summary class="cursor-pointer font-semibold text-danger">Suspendre la boutique</summary>
                <form method="POST" action="{{ route('team.stores.suspend', $store->id) }}" class="mt-3" novalidate>
                    @csrf
                    <div class="field">
                        <label class="label" for="reason">Motif (le commerçant le verra)</label>
                        <input class="input @error('reason') is-invalid @enderror" id="reason" name="reason" value="{{ old('reason') }}" maxlength="255" placeholder="Ex. plaintes de clients non livrés">
                        @error('reason')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <button class="btn btn-danger" type="submit">Suspendre maintenant</button>
                </form>
            </details>
        @endif
    </section>

    @if ($actions->isNotEmpty())
        <section class="section border-b-0">
            <h2 class="section-title">Historique</h2>
            <ul class="space-y-2 text-sm">
                @foreach ($actions as $action)
                    <li>
                        <span class="font-semibold">{{ $action->label() }}</span>@if ($action->details) · {{ $action->details }}@endif
                        <span class="block text-xs text-text-muted">{{ $action->created_at->timezone($tz)->format('d/m/Y H:i') }} · {{ $action->user?->name ?? 'compte supprimé' }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.admin>
