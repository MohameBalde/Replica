@php
    $tz = \App\Support\Period::TZ;
    $plural = fn (int $n, string $one, string $many) => $n.' '.($n > 1 ? $many : $one);
    // Sur ordinateur, les blocs se rangent en grille : séparateur fin au lieu de la bande grise du téléphone.
    $cell = 'lg:border-b lg:border-border';
@endphp
<x-layouts.admin title="Accueil" wide>
    @unless ($store->is_open)
        <form method="POST" action="{{ route('admin.settings.store.toggle') }}" class="mx-4 mt-3 rounded-md bg-danger-soft p-3 text-on-danger-soft lg:mx-4 lg:mt-4 lg:flex lg:items-center lg:justify-between lg:gap-4">
            @csrf
            <input type="hidden" name="is_open" value="1">
            <p class="text-sm font-semibold">Votre boutique est fermée : les clients voient vos produits mais ne peuvent pas commander.</p>
            <button class="btn btn-primary mt-3 lg:mt-0 lg:w-auto lg:shrink-0 lg:px-6" type="submit">Ouvrir ma boutique</button>
        </form>
    @endunless

    @if ($starting)
        {{-- ===== Bien démarrer : tant qu'aucune commande (non annulée) n'est arrivée ===== --}}
        @php
            $c = $checklist;
            $labels = [
                'store' => 'Boutique créée',
                'product' => $c['hiddenProducts'] ? 'Vos produits sont masqués : rendez-en un visible' : 'Ajoutez votre premier produit',
                'payment' => 'Recevez Orange Money ou MTN MoMo',
                'share' => 'Partagez votre lien',
                'order' => 'Recevez votre première commande',
            ];
        @endphp
        <div class="xl:grid xl:grid-cols-3">
            <section class="section {{ $cell }} xl:col-span-2 xl:row-span-2">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 class="section-title mb-0">Bien démarrer</h2>
                    <p class="text-sm text-text-muted">{{ $c['done'] }} {{ $c['done'] > 1 ? 'étapes' : 'étape' }} sur 5</p>
                </div>
                <div class="mt-2 flex gap-1" aria-hidden="true">
                    @foreach ($c['steps'] as $done)<div class="h-1.5 flex-1 rounded-pill {{ $done ? 'bg-accent' : 'bg-surface' }}"></div>@endforeach
                </div>

                <ol class="mt-3 space-y-1">
                    @foreach ($c['steps'] as $key => $done)
                        @php $isNext = $key === $c['next']; @endphp
                        <li class="rounded-md {{ $isNext ? 'bg-accent-soft/40 p-3' : 'px-3 py-2' }}">
                            <div class="flex min-h-tap items-center gap-3">
                                @if ($done)
                                    <span class="grid size-7 shrink-0 place-items-center rounded-pill bg-success-soft text-success"><x-icon name="check" :size="16" /></span>
                                @else
                                    <span class="grid size-7 shrink-0 place-items-center rounded-pill border border-border-input text-sm font-semibold">{{ $loop->iteration }}</span>
                                @endif
                                <span class="font-semibold {{ $done ? 'text-text-muted' : '' }}">{{ $labels[$key] }}<span class="sr-only">{{ $done ? ' (fait)' : '' }}</span></span>
                            </div>
                            @if ($isNext)
                                <div class="mt-1 pl-10">
                                    @switch ($key)
                                        @case('product')
                                            <p class="text-sm text-text-muted">Une photo nette et un prix en GNF suffisent pour commencer.</p>
                                            <a href="{{ $c['hiddenProducts'] ? route('admin.products.index') : route('admin.products.create') }}" class="btn btn-primary btn-lg mt-3 lg:w-auto lg:px-8">{{ $c['hiddenProducts'] ? 'Voir mes produits' : 'Ajouter un produit' }}</a>
                                            @break
                                        @case('payment')
                                            <p class="text-sm text-text-muted">Le paiement à la livraison est déjà activé. Ajoutez votre numéro pour être payé avant de livrer.</p>
                                            <a href="{{ route('admin.payments.edit') }}" class="btn btn-primary btn-lg mt-3 lg:w-auto lg:px-8">Ajouter mon numéro</a>
                                            @break
                                        @case('share')
                                            <p class="text-sm text-text-muted">Mettez-le dans votre statut WhatsApp, votre bio TikTok ou votre page Facebook.</p>
                                            <a href="{{ $c['shareUrl'] }}" class="btn btn-whatsapp btn-lg mt-3 lg:w-auto lg:px-8" target="_blank" rel="noopener"><x-icon name="message" :size="20" /> Partager sur WhatsApp</a>
                                            <a href="{{ route('admin.share') }}" class="mt-2 inline-flex min-h-tap items-center text-sm font-semibold text-accent lg:ml-4">Autres réseaux →</a>
                                            @break
                                        @case('order')
                                            <p class="text-sm text-text-muted">Elle arrive sur votre WhatsApp et ici. Pour essayer, commandez sur votre boutique puis annulez la commande : elle ne comptera pas dans vos chiffres et le stock revient.</p>
                                            <a href="{{ route('shop.show', $store) }}" class="btn btn-secondary btn-lg mt-3 lg:w-auto lg:px-8" target="_blank" rel="noopener">Voir ma boutique comme un client</a>
                                            @break
                                    @endswitch
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ol>

                @if ($visitsRecent > 0)
                    <p class="mt-4 text-sm">Votre boutique a été ouverte {{ $plural($visitsRecent, 'fois', 'fois') }} ces 7 derniers jours. Vos ventes s'afficheront ici dès la première commande.
                        <a href="{{ route('admin.share') }}" class="font-semibold text-accent">Voir le détail →</a></p>
                @endif
                <div class="lg:hidden">@include('admin._dashboard_low_stock')</div>
            </section>

            {{-- Ordinateur : le lien de la boutique toujours sous la main. --}}
            <section class="section {{ $cell }} hidden lg:block xl:col-start-3 xl:border-l">
                <h2 class="section-title">Votre lien</h2>
                <p class="rounded-md bg-surface px-3 py-2 font-mono text-sm break-all">{{ route('shop.show', $store) }}</p>
                <a href="{{ $c['shareUrl'] }}" class="btn btn-whatsapp mt-3" target="_blank" rel="noopener"><x-icon name="message" :size="20" /> Partager sur WhatsApp</a>
                <img src="{{ route('admin.share.qr') }}" width="160" height="160" loading="lazy" alt="QR code de la boutique" class="mx-auto mt-4 rounded-md border border-border p-2">
                <a href="{{ route('admin.share.qr', ['download' => 1]) }}" class="mt-2 block text-center text-sm font-semibold text-accent">Télécharger pour le comptoir</a>
                @include('admin._dashboard_low_stock')
            </section>
        </div>
    @else
        {{-- ===== Accueil normal ===== --}}
        @php
            $t = $todo;
            $nothingToDo = ! $t['to_verify'] && ! $t['pending'] && ! $t['cod_count'] && $lowStock->isEmpty();
            $periods = ['aujourdhui' => 'Aujourd\'hui', '7j' => '7 jours', '30j' => '30 jours'];
            $s = $sales;
            $today = \Carbon\CarbonImmutable::now($tz);
            $range = $days === 1 ? 'aujourd\'hui ('.$today->format('d/m').')' : 'du '.$from->format('d/m').' au '.$today->format('d/m');
        @endphp
        {{-- Grand écran (xl) : trois colonnes. En dessous, une seule colonne, comme sur téléphone. --}}
        <div class="xl:grid xl:grid-cols-3">
            {{-- À faire maintenant : en premier sur téléphone, à droite sur grand écran. --}}
            <section class="section {{ $cell }} xl:col-start-3 xl:row-start-1 xl:border-l">
                <h2 class="section-title">À faire maintenant</h2>
                @if ($nothingToDo)
                    <div class="flex items-center gap-3 rounded-md bg-success-soft p-3 text-on-success-soft">
                        <x-icon name="check" :size="20" class="shrink-0" />
                        <p class="text-sm">Rien d'urgent : aucun paiement à vérifier, aucune commande en attente.
                            <a href="{{ route('admin.share') }}" class="font-semibold underline">Partager ma boutique →</a></p>
                    </div>
                @else
                    <ul class="space-y-2">
                        @if ($t['to_verify'])
                            <li>
                                <a href="{{ route('admin.orders.index', ['paiement' => 'confirming']) }}" class="flex min-h-tap items-center gap-3 rounded-md bg-warning-soft px-3 py-2.5 text-on-warning-soft">
                                    <span class="w-9 shrink-0 text-center text-xl font-bold tabular-nums">{{ $t['to_verify'] }}</span>
                                    <span class="min-w-0 flex-1"><span class="block font-semibold">{{ $t['to_verify'] > 1 ? 'paiements à vérifier' : 'paiement à vérifier' }}</span>
                                        <span class="block text-sm">Le client a envoyé une capture. Vérifiez sur votre téléphone avant de marquer « Payée ».</span></span>
                                    <span aria-hidden="true">→</span>
                                </a>
                            </li>
                        @endif
                        @if ($t['pending'])
                            <li>
                                <a href="{{ route('admin.orders.index', ['statut' => 'pending']) }}" class="flex min-h-tap items-center gap-3 rounded-md px-3 py-2.5 {{ $t['pending_old'] ? 'bg-warning-soft text-on-warning-soft' : 'bg-accent-soft/50' }}">
                                    <span class="w-9 shrink-0 text-center text-xl font-bold tabular-nums">{{ $t['pending'] }}</span>
                                    <span class="min-w-0 flex-1"><span class="block font-semibold">{{ $t['pending'] > 1 ? 'commandes en attente' : 'commande en attente' }}</span>
                                        <span class="block text-sm">
                                            @if ($t['pending_old'])
                                                dont {{ $t['pending_old'] }} depuis plus de 2 jours : confirmez ou annulez, {{ $t['pending_old'] > 1 ? 'elles bloquent' : 'elle bloque' }} du stock.
                                            @elseif ($t['pending_no_wa'])
                                                dont {{ $t['pending_no_wa'] }} où le client n'a pas ouvert WhatsApp : écrivez-lui.
                                            @else
                                                À confirmer avec le client sur WhatsApp.
                                            @endif
                                        </span></span>
                                    <span aria-hidden="true">→</span>
                                </a>
                            </li>
                        @endif
                        @if ($t['cod_count'])
                            <li>
                                <a href="{{ route('admin.orders.index', ['paiement' => 'cod']) }}" class="flex min-h-tap items-center gap-3 rounded-md bg-surface px-3 py-2.5">
                                    <span class="w-9 shrink-0 text-center text-xl font-bold tabular-nums">{{ $t['cod_count'] }}</span>
                                    <span class="min-w-0 flex-1"><span class="block font-semibold">à encaisser à la livraison · <span class="whitespace-nowrap">{{ gnf($t['cod_amount'], false) }}</span> GNF</span>
                                        <span class="block text-sm text-text-muted">
                                            @if ($t['cod_completed'])
                                                dont {{ $plural($t['cod_completed'], 'terminée', 'terminées') }} : si l'argent est reçu, marquez « Payée ».
                                            @else
                                                L'argent est chez le client ou le livreur.
                                            @endif
                                        </span></span>
                                    <span aria-hidden="true">→</span>
                                </a>
                            </li>
                        @endif
                    </ul>
                    @include('admin._dashboard_low_stock')
                @endif
                @unless ($mobileMoneyReady)
                    <a href="{{ route('admin.payments.edit') }}" class="mt-3 block text-sm text-text-muted">Conseil : ajoutez Orange Money ou MTN MoMo pour être payé avant de livrer <span class="font-semibold text-accent">→</span></a>
                @endunless
            </section>

            {{-- Vos ventes --}}
            <section class="section {{ $cell }} xl:col-span-2 xl:col-start-1 xl:row-start-1">
                <div class="lg:flex lg:items-center lg:justify-between">
                    <h2 class="section-title lg:mb-0">Vos ventes</h2>
                    <div class="flex gap-2" role="group" aria-label="Période">
                        @foreach ($periods as $key => $label)
                            <a href="{{ route('admin.home', ['periode' => $key]) }}" @if ($periodKey === $key) aria-current="true" @endif
                               class="badge min-h-tap px-4 text-sm {{ $periodKey === $key ? 'bg-accent text-on-accent' : 'badge-neutral' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-2 gap-2 text-center lg:gap-3">
                    <div class="rounded-md bg-surface p-3">
                        <p class="text-xl font-bold tabular-nums">{{ $s['orders'] }}</p>
                        <p class="text-xs text-text-muted">{{ $s['orders'] > 1 ? 'commandes reçues' : 'commande reçue' }}</p>
                    </div>
                    <a href="{{ route('admin.share', ['periode' => $periodKey]) }}" class="rounded-md bg-surface p-3">
                        <p class="text-xl font-bold tabular-nums">{{ $visitsRecent }}</p>
                        <p class="text-xs text-text-muted">{{ $visitsRecent > 1 ? 'visites de la boutique' : 'visite de la boutique' }}</p>
                    </a>
                    @if ($s['orders'] > 0)
                        @foreach ([[$s['paid'], 'GNF déjà payés'], [$s['due'], 'GNF pas encore payés']] as [$amount, $label])
                            <div class="rounded-md bg-surface p-3">
                                <p class="{{ strlen(gnf($amount, false)) > 10 ? 'text-lg' : 'text-xl' }} font-bold whitespace-nowrap tabular-nums lg:text-xl">{{ gnf($amount, false) }}</p>
                                <p class="text-xs text-text-muted">{{ $label }}</p>
                            </div>
                        @endforeach
                    @endif
                </div>

                @if ($s['orders'] === 0)
                    <div class="mt-3 rounded-md border border-border p-4 text-center">
                        <p class="font-semibold">{{ ['aujourdhui' => 'Pas encore de commande aujourd\'hui.', '7j' => 'Aucune commande ces 7 derniers jours.', '30j' => 'Aucune commande ces 30 derniers jours.'][$periodKey] }}</p>
                        <p class="mt-1 text-sm text-text-muted">Partagez un produit sur votre statut WhatsApp pour relancer les ventes.</p>
                        <a href="{{ route('admin.share') }}" class="btn btn-secondary mt-3 lg:w-auto lg:px-6">Partager ma boutique</a>
                    </div>
                @else
                    <p class="mt-3 text-sm">Valeur des commandes : <strong class="whitespace-nowrap">{{ gnf($s['total']) }}</strong> (livraison comprise)</p>
                    @if ($s['partial'])
                        <p class="text-xs text-text-muted">Dont {{ $s['partial'] }} payée{{ $s['partial'] > 1 ? 's' : '' }} en partie, comptée{{ $s['partial'] > 1 ? 's' : '' }} en entier : le montant déjà reçu n'est pas enregistré.</p>
                    @endif
                    @if ($s['due'] > 0)
                        <p class="text-xs text-text-muted">Commande payée ? Ouvrez-la et choisissez « Payée ».</p>
                    @endif

                    @if ($days > 1)
                        @php
                            $series = $s['series'];
                            $w = 10;
                            $aria = 'Commandes par jour : '.$series->map(fn ($d) => \Carbon\CarbonImmutable::parse($d['day'])->format('d/m').' : '.$d['n'])->implode(', ');
                        @endphp
                        <div class="mt-4">
                            @if ($days === 7)
                                <div class="grid grid-cols-7 text-center text-xs font-semibold tabular-nums">
                                    @foreach ($series as $d)<span>{{ $d['n'] ?: '' }}</span>@endforeach
                                </div>
                            @endif
                            <svg viewBox="0 0 {{ $days * $w }} 100" preserveAspectRatio="none" class="h-24 w-full lg:h-32" role="img" aria-label="{{ $aria }}">
                                @foreach ($series as $i => $d)
                                    <rect x="{{ $i * $w + 1.5 }}" y="{{ 100 - $d['height'] }}" width="{{ $w - 3 }}" height="{{ $d['height'] }}" rx="1.5" class="{{ $d['n'] ? 'fill-accent' : 'fill-border' }}"/>
                                @endforeach
                            </svg>
                            @if ($days === 7)
                                <div class="grid grid-cols-7 text-center text-xs text-text-muted">
                                    @foreach ($series as $d)
                                        <span class="{{ $d['label'] === 'Auj.' ? 'font-semibold text-text' : '' }}">{{ $d['label'] }}
                                            @if ($d['amount'])<span class="hidden whitespace-nowrap lg:block">{{ gnf($d['amount'], false) }}</span>@endif</span>
                                    @endforeach
                                </div>
                            @else
                                <div class="flex justify-between text-xs text-text-muted">
                                    <span>{{ $from->format('d/m') }}</span><span class="font-semibold text-text">Auj.</span>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if ($s['topSources'])
                        @php $names = collect($s['topSources'])->map(fn ($k) => \App\Models\Order::SOURCES[$k]); @endphp
                        <p class="mt-3 text-sm">Le plus de commandes vient de <strong>{{ $names->join(', ', ' et ') }}</strong>
                            ({{ $s['topSourceCount'] }}{{ $names->count() > 1 ? ' chacun' : '' }} sur {{ $s['orders'] }}).
                            <a href="{{ route('admin.share', ['periode' => $periodKey]) }}" class="font-semibold text-accent">Voir le détail →</a></p>
                    @else
                        <p class="mt-3 text-sm">Pour savoir d'où viennent vos clients, utilisez les liens de la page <a href="{{ route('admin.share') }}" class="font-semibold text-accent">Partager →</a></p>
                    @endif
                @endif
                <p class="mt-3 text-xs text-text-muted">Commandes reçues {{ $range }} (heure de Conakry), hors annulées et remboursées. Montants livraison comprise. Une visite = une ouverture de la boutique, y compris les vôtres.</p>
            </section>

            {{-- Dernières commandes --}}
            @if ($recentOrders->isNotEmpty())
                <section class="section {{ $cell }} px-0 xl:col-span-2 xl:col-start-1 xl:row-start-2 xl:border-b-0">
                    <h2 class="section-title px-4">Dernières commandes</h2>
                    <ul class="divide-y divide-border border-y border-border">
                        @foreach ($recentOrders as $order)
                            @include('admin.orders._row')
                        @endforeach
                    </ul>
                    <a href="{{ route('admin.orders.index') }}" class="flex min-h-tap items-center px-4 text-sm font-semibold text-accent">Toutes les commandes →</a>
                </section>
            @endif

            {{-- Ce qui se vend --}}
            @if ($topProducts->isNotEmpty())
                <section class="section {{ $cell }} lg:border-b-0 xl:col-start-3 xl:row-start-2 xl:border-l">
                    <h2 class="section-title">Ce qui se vend</h2>
                    <ol class="divide-y divide-border">
                        @foreach ($topProducts as $p)
                            @php $qty = (int) $p->qty; $label = $p->product_id ? $p->name : $p->name.' (retiré)'; @endphp
                            <li>
                                @if ($p->product_id)<a href="{{ route('admin.products.edit', $p->product_id) }}" class="flex min-h-tap items-center gap-3">@else<div class="flex min-h-tap items-center gap-3">@endif
                                    <span class="w-5 shrink-0 text-text-muted">{{ $loop->iteration }}</span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-semibold">{{ $label }}</span>
                                        <span class="block text-sm text-text-muted">{{ $qty }} {{ $qty > 1 ? 'commandés' : 'commandé' }}</span>
                                    </span>
                                @if ($p->product_id)</a>@else</div>@endif
                            </li>
                        @endforeach
                    </ol>
                    <p class="mt-2 text-xs text-text-muted">Sur la période choisie, hors commandes annulées et remboursées.</p>
                    <a href="{{ route('admin.products.index') }}" class="inline-flex min-h-tap items-center text-sm font-semibold text-accent">Voir mes produits →</a>
                </section>
            @endif
        </div>
    @endif
</x-layouts.admin>
