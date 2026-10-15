<x-layouts.admin title="Commandes">
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

    @if ($orders->isEmpty())
        <div class="px-4 py-12 text-center">
            <p class="text-lg font-semibold">Pas encore de commande ici.</p>
            <p class="mt-2 text-text-muted">Partagez le lien de votre boutique pour recevoir les premières.</p>
            <p class="mt-4 rounded-md bg-surface px-3 py-2 font-mono text-sm break-all">{{ route('shop.show', $currentStore) }}</p>
        </div>
    @else
        <ul class="divide-y divide-border">
            @foreach ($orders as $order)
                <li>
                    <a href="{{ route('admin.orders.show', $order->id) }}" class="block px-4 py-3">
                        <div class="flex items-baseline gap-2">
                            <span class="font-bold">#{{ $order->number }}</span>
                            <span class="min-w-0 flex-1 truncate">{{ $order->customer_name }}</span>
                            <span class="price">{{ gnf($order->total) }}</span>
                        </div>
                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                            @include('admin.orders._badges')
                            @if ($order->source !== 'direct')
                                <span class="text-xs text-text-muted">via {{ \App\Models\Order::SOURCES[$order->source] ?? $order->source }}</span>
                            @endif
                            <span class="ml-auto text-xs text-text-muted">{{ $order->created_at->timezone('Africa/Conakry')->format('d/m H:i') }}</span>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="p-4">{{ $orders->links('pagination::simple-tailwind') }}</div>
    @endif
</x-layouts.admin>
