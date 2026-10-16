<x-layouts.admin title="Clients" :back="route('admin.orders.index')">
    <form method="GET" class="p-4" role="search">
        <label class="sr-only" for="q">Chercher un client</label>
        <input class="input" type="search" id="q" name="q" value="{{ $search }}" placeholder="Nom ou numéro">
    </form>

    @if ($customers->isEmpty())
        <div class="px-4 py-12 text-center">
            <p class="text-lg font-semibold">{{ $search !== '' ? 'Aucun client trouvé.' : 'Vos clients apparaîtront ici après leur première commande.' }}</p>
        </div>
    @else
        <ul class="divide-y divide-border border-y border-border">
            @foreach ($customers as $customer)
                <li>
                    <a href="{{ route('admin.customers.show', $customer->id) }}" class="flex items-center gap-3 px-4 py-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $customer->name }}</p>
                            <p class="text-sm text-text-muted">{{ phone_display($customer->phone) }} · {{ $customer->orders_count }} commande{{ $customer->orders_count > 1 ? 's' : '' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="price">{{ gnf($customer->spent ?? 0) }}</p>
                            @if ($customer->cash_on_delivery_blocked)
                                <span class="badge badge-danger mt-1">Payer avant</span>
                            @elseif ($customer->cancelled_count)
                                <span class="badge badge-warning mt-1">{{ $customer->cancelled_count }} annulée{{ $customer->cancelled_count > 1 ? 's' : '' }}</span>
                            @endif
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="p-4">{{ $customers->links('pagination::simple-tailwind') }}</div>
    @endif
</x-layouts.admin>
