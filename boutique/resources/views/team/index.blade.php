<x-layouts.admin title="Gestion Wanllan" :back="route('admin.orders.index')">
    <section class="section">
        <div class="grid grid-cols-2 gap-2 text-center">
            <div class="rounded-md bg-surface p-3">
                <p class="text-xl font-bold">{{ $stats['stores'] }}</p>
                <p class="text-xs text-text-muted">boutique{{ $stats['stores'] > 1 ? 's' : '' }} · +{{ $stats['new'] }} en 7 jours</p>
            </div>
            <div class="rounded-md bg-surface p-3">
                <p class="text-xl font-bold">{{ $stats['paid'] }}</p>
                <p class="text-xs text-text-muted">abonnée{{ $stats['paid'] > 1 ? 's' : '' }} payante{{ $stats['paid'] > 1 ? 's' : '' }}</p>
            </div>
            <div class="rounded-md bg-surface p-3">
                <p class="text-base font-bold">{{ gnf($stats['month']) }}</p>
                <p class="text-xs text-text-muted">encaissés ce mois-ci</p>
            </div>
            <a href="{{ route('team.dashboard', ['filtre' => 'relancer']) }}" class="rounded-md p-3 {{ $stats['remind'] ? 'bg-warning-soft text-on-warning-soft' : 'bg-surface' }}">
                <p class="text-xl font-bold">{{ $stats['remind'] }}</p>
                <p class="text-xs">à relancer →</p>
            </a>
        </div>
    </section>

    <form method="GET" class="px-4 pt-4" role="search">
        @if ($filter)<input type="hidden" name="filtre" value="{{ $filter }}">@endif
        <label class="sr-only" for="q">Chercher une boutique</label>
        <input class="input" type="search" id="q" name="q" value="{{ $search }}" placeholder="Nom, lien ou numéro">
    </form>

    <div class="flex gap-2 overflow-x-auto px-4 py-3" role="group" aria-label="Filtrer les boutiques">
        <a href="{{ route('team.dashboard', array_filter(['q' => $search])) }}" @if (! $filter) aria-current="true" @endif
           class="badge min-h-8 shrink-0 px-3 text-sm {{ ! $filter ? 'bg-accent text-on-accent' : 'badge-neutral' }}">Toutes</a>
        @foreach (\App\Http\Controllers\Team\StoreController::FILTERS as $key => $label)
            @php $n = ['payantes' => $stats['paid'], 'relancer' => $stats['remind'], 'suspendues' => $stats['suspended']][$key]; @endphp
            <a href="{{ route('team.dashboard', array_filter(['filtre' => $key, 'q' => $search])) }}" @if ($filter === $key) aria-current="true" @endif
               class="badge min-h-8 shrink-0 px-3 text-sm {{ $filter === $key ? 'bg-accent text-on-accent' : 'badge-neutral' }}">
                {{ $label }} ({{ $n }})
            </a>
        @endforeach
    </div>

    @if ($stores->isEmpty())
        <div class="px-4 py-12 text-center">
            <p class="text-lg font-semibold">{{ $search !== '' || $filter ? 'Aucune boutique ici.' : 'Pas encore de boutique.' }}</p>
        </div>
    @else
        <ul class="divide-y divide-border border-y border-border">
            @foreach ($stores as $store)
                <li>
                    <a href="{{ route('team.stores.show', $store->id) }}" class="block px-4 py-3">
                        <div class="flex items-baseline gap-2">
                            <span class="min-w-0 flex-1 truncate font-semibold">{{ $store->name }}</span>
                            <span class="shrink-0 text-xs text-text-muted">{{ $store->city }}</span>
                        </div>
                        <p class="truncate text-sm text-text-muted">
                            {{ $store->user?->name }} · {{ phone_display($store->whatsapp_number) }}
                        </p>
                        <p class="text-sm text-text-muted">
                            {{ $store->products_count }} produit{{ $store->products_count > 1 ? 's' : '' }} ·
                            {{ $store->orders_count }} commande{{ $store->orders_count > 1 ? 's' : '' }}@if ($store->orders_max_created_at)
                                · dernière le {{ \Illuminate\Support\Carbon::parse($store->orders_max_created_at)->timezone('Africa/Conakry')->format('d/m') }}@endif
                        </p>
                        <div class="mt-1 flex flex-wrap gap-1.5">@include('team._status')</div>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="p-4">{{ $stores->links('pagination::simple-tailwind') }}</div>
    @endif

    @if ($actions->isNotEmpty())
        <section class="section border-b-0">
            <h2 class="section-title">Dernières actions</h2>
            <ul class="space-y-2 text-sm">
                @foreach ($actions as $action)
                    <li>
                        <span class="font-semibold">{{ $action->label() }}</span> · {{ $action->store_name }}
                        <span class="block text-xs text-text-muted">{{ $action->created_at->timezone('Africa/Conakry')->format('d/m/Y H:i') }} · {{ $action->user?->name ?? 'compte supprimé' }}@if ($action->details) · {{ $action->details }}@endif</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.admin>
