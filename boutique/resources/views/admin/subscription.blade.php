@php $plan = $plans[$current]; @endphp
<x-layouts.admin title="Réglages">
    @include('admin.settings._tabs')

    <section class="section">
        <p class="text-sm text-text-muted">Votre formule</p>
        <p class="text-xl font-bold">{{ $plan['name'] }}</p>
        @if ($current === \App\Support\Plans::FREE)
            <p class="mt-1 text-sm">{{ $productCount }} produit{{ $productCount > 1 ? 's' : '' }} sur {{ $plan['max_products'] }}.</p>
        @else
            <p class="mt-1 text-sm">Payée jusqu'au <strong>{{ $store->plan_expires_at->timezone('Africa/Conakry')->translatedFormat('j F Y') }}</strong>
                ({{ $store->planDaysLeft() }} jour{{ $store->planDaysLeft() > 1 ? 's' : '' }}).</p>
        @endif
        <p class="mt-3 rounded-md bg-success-soft p-3 text-sm text-on-success-soft">
            <strong>Rien n'est prélevé automatiquement.</strong> Vous payez un mois ou une année à la fois, par Orange Money ou MTN.
            À la fin, votre boutique repasse simplement en Découverte : vous ne perdez ni vos produits ni vos commandes.
        </p>
    </section>

    <section class="section">
        <h2 class="section-title">Les formules</h2>
        <div class="space-y-3">
            @foreach ($plans as $key => $p)
                <div class="card p-4 {{ $key === $current ? 'border-2 border-accent' : '' }}">
                    <div class="flex items-baseline justify-between gap-2">
                        <p class="text-lg font-bold">{{ $p['name'] }}</p>
                        <p class="price">{{ $p['monthly'] ? gnf($p['monthly']).' / mois' : 'Gratuit' }}</p>
                    </div>
                    <p class="text-sm text-text-muted">{{ $p['for'] }}</p>
                    @if ($p['monthly'])
                        <p class="mt-1 text-sm">ou <strong>{{ gnf(\App\Support\Plans::yearly($key)) }} / an</strong> (2 mois offerts)</p>
                    @endif
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($p['features'] as $f)<li class="flex gap-2"><x-icon name="check" :size="16" class="mt-0.5 shrink-0 text-success" /> {{ $f }}</li>@endforeach
                    </ul>
                    @if ($key === $current)<span class="badge badge-accent mt-3">Votre formule</span>@endif
                </div>
            @endforeach
        </div>
    </section>

    <section class="section">
        <h2 class="section-title">Comment payer</h2>
        <ol class="list-decimal space-y-2 pl-5 text-sm">
            <li>Envoyez le montant par <strong>Orange Money</strong> au <strong>{{ phone_display(config('wanllan.pay_orange_money')) }}</strong> ({{ config('wanllan.pay_name') }}).</li>
            <li>Envoyez-nous la capture sur WhatsApp avec le nom de votre boutique.</li>
            <li>Nous activons votre formule et vous recevez la date de fin. C'est tout.</li>
        </ol>
        <a href="{{ $supportUrl }}" class="btn btn-whatsapp mt-4" target="_blank" rel="noopener"><x-icon name="message" :size="20" /> Écrire à Wanllan</a>
        <p class="help text-center">Support : {{ config('wanllan.support_hours') }}.</p>
    </section>

    @if ($payments->isNotEmpty())
        <section class="section border-b-0">
            <h2 class="section-title">Vos paiements</h2>
            <ul class="divide-y divide-border text-sm">
                @foreach ($payments as $pay)
                    <li class="flex justify-between gap-3 py-2">
                        <span>{{ $plans[$pay->plan]['name'] ?? $pay->plan }} · {{ $pay->months }} mois<br>
                            <span class="text-text-muted">du {{ $pay->period_start->timezone('Africa/Conakry')->format('d/m/Y') }} au {{ $pay->period_end->timezone('Africa/Conakry')->format('d/m/Y') }}</span></span>
                        <span class="price">{{ gnf($pay->amount) }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.admin>
