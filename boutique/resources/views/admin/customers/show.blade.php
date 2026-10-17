@php
    $active = $orders->where('status', '!=', 'cancelled');
@endphp
<x-layouts.admin :title="$customer->name" :back="route('admin.customers.index')">
    <section class="section">
        <p class="text-text-muted">{{ phone_display($customer->phone) }}</p>
        @if ($customer->address)<p class="mt-1">{{ $customer->address }}</p>@endif
        <div class="mt-3 grid grid-cols-3 gap-2 text-center">
            <div class="rounded-md bg-surface p-2"><p class="text-lg font-bold">{{ $active->count() }}</p><p class="text-xs text-text-muted">commandes</p></div>
            <div class="rounded-md bg-surface p-2"><p class="text-sm font-bold">{{ gnf($active->sum('total')) }}</p><p class="text-xs text-text-muted">dépensés</p></div>
            <div class="rounded-md bg-surface p-2"><p class="text-lg font-bold">{{ $orders->where('status', 'cancelled')->count() }}</p><p class="text-xs text-text-muted">annulées</p></div>
        </div>
        <a href="{{ \App\Services\WhatsAppMessage::url($customer->phone, 'Bonjour '.$customer->name.', ici '.$currentStore->name.'.') }}" class="btn btn-whatsapp mt-4" target="_blank" rel="noopener">
            <x-icon name="message" :size="20" /> Écrire sur WhatsApp
        </a>
    </section>

    <section class="section">
        <h2 class="section-title">Paiement à la livraison</h2>
        <form method="POST" action="{{ route('admin.customers.cod', $customer->id) }}" class="flex items-center gap-3">
            @csrf
            <p class="flex-1 text-sm">
                @if ($customer->cash_on_delivery_blocked)
                    <span class="font-semibold text-danger">Bloqué</span> : ce client doit payer avant la livraison (Orange Money, MTN…).
                @else
                    Autorisé. Bloquez-le si ce client a refusé un colis.
                @endif
            </p>
            <button class="btn {{ $customer->cash_on_delivery_blocked ? 'btn-secondary' : 'btn-danger' }} w-auto shrink-0" type="submit">
                {{ $customer->cash_on_delivery_blocked ? 'Débloquer' : 'Bloquer' }}
            </button>
        </form>
    </section>

    <section class="section border-b-0 px-0">
        <h2 class="section-title px-4">Commandes</h2>
        <ul class="divide-y divide-border">
            @foreach ($orders as $order)
                <li>
                    <a href="{{ route('admin.orders.show', $order->id) }}" class="block px-4 py-3">
                        <div class="flex items-baseline gap-2">
                            <span class="font-bold">#{{ $order->number }}</span>
                            <span class="flex-1 text-sm text-text-muted">{{ $order->created_at->timezone('Africa/Conakry')->format('d/m/Y') }}</span>
                            <span class="price">{{ gnf($order->total) }}</span>
                        </div>
                        <div class="mt-1 flex flex-wrap gap-1.5">@include('admin.orders._badges')</div>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
</x-layouts.admin>
