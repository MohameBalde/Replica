{{-- Une commande dans une liste (Commandes, Accueil). --}}
<li>
    <a href="{{ route('admin.orders.show', $order->id) }}" class="block px-4 py-3">
        <div class="flex items-baseline gap-2">
            <span class="font-bold">#{{ $order->number }}</span>
            <span class="min-w-0 flex-1 truncate">{{ $order->customer_name }}</span>
            <span class="price">{{ gnf($order->total) }}</span>
        </div>
        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
            @include('admin.orders._badges')
            {{-- Le client n'a pas cliqué « Envoyer sur WhatsApp » : le commerçant n'a peut-être pas reçu le message. --}}
            @if ($order->status === 'pending' && ! $order->whatsapp_sent_at)
                <span class="badge badge-warning">WhatsApp non envoyé</span>
            @endif
            @if ($order->source !== 'direct')
                <span class="text-xs text-text-muted">via {{ \App\Models\Order::SOURCES[$order->source] ?? $order->source }}</span>
            @endif
            <span class="ml-auto text-xs text-text-muted">{{ $order->created_at->timezone('Africa/Conakry')->format('d/m H:i') }}</span>
        </div>
    </a>
</li>
