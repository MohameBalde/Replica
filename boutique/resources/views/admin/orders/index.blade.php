<x-layouts.admin title="Commandes" wide>
    <nav class="flex gap-5 overflow-x-auto border-b border-border px-4" aria-label="Filtrer les commandes">
        @foreach (\App\Http\Controllers\Admin\OrderController::TABS as $key => $label)
            @php $n = $key === 'all' ? $counts->sum() : ($counts[$key] ?? 0); @endphp
            <a href="{{ route('admin.orders.index', $key === 'all' ? [] : ['statut' => $key]) }}"
               @if ($tab === $key) aria-current="page" @endif
               class="whitespace-nowrap py-3 text-sm font-semibold {{ $tab === $key ? 'text-accent shadow-[inset_0_-2px_0_var(--color-accent)]' : 'text-text-muted' }}">
                {{ $label }} @if ($n)<span class="font-normal">({{ $n }})</span>@endif
            </a>
        @endforeach
    </nav>

    <div class="flex gap-2 overflow-x-auto px-4 py-3" role="group" aria-label="Filtrer par paiement">
        @foreach (\App\Http\Controllers\Admin\OrderController::PAYMENT_FILTERS as $key => $label)
            @php $on = $payment === $key; @endphp
            <a href="{{ route('admin.orders.index', array_filter(['statut' => $tab === 'all' ? null : $tab, 'paiement' => $on ? null : $key])) }}"
               @if ($on) aria-current="true" @endif
               class="badge min-h-8 shrink-0 px-3 text-sm {{ $on ? 'bg-accent text-on-accent' : 'badge-neutral' }}">
                {{ $label }}@if ($key === 'confirming' && $toVerify) ({{ $toVerify }})@endif
            </a>
        @endforeach
        <a href="{{ route('admin.customers.index') }}" class="badge badge-neutral ml-auto min-h-8 shrink-0 gap-1 px-3 text-sm"><x-icon name="users" :size="14" /> Clients</a>
    </div>

    @if ($toVerify && $payment !== 'confirming')
        <a href="{{ route('admin.orders.index', ['paiement' => 'confirming']) }}" class="mx-4 mb-2 block rounded-md bg-warning-soft px-3 py-2 text-sm font-semibold text-on-warning-soft">
            {{ $toVerify }} paiement{{ $toVerify > 1 ? 's' : '' }} à vérifier →
        </a>
    @endif

    @if ($orders->isEmpty())
        <div class="px-4 py-12 text-center">
            <p class="text-lg font-semibold">Pas encore de commande ici.</p>
            <p class="mt-2 text-text-muted">Partagez le lien de votre boutique pour recevoir les premières.</p>
            <p class="mt-4 rounded-md bg-surface px-3 py-2 font-mono text-sm break-all">{{ route('shop.show', $currentStore) }}</p>
        </div>
    @else
        <ul class="divide-y divide-border">
            @foreach ($orders as $order)
                @include('admin.orders._row')
            @endforeach
        </ul>
        <div class="p-4">{{ $orders->links('pagination::simple-tailwind') }}</div>
    @endif
</x-layouts.admin>
