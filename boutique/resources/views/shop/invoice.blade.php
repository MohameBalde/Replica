<x-layouts.shop :store="$store" :title="'Commande #'.$order->number">
    <header class="px-4 pt-6 pb-2">
        <p class="text-sm font-semibold text-text-muted">{{ $store->name }}</p>
        <h1 class="text-xl font-bold">Commande #{{ $order->number }}</h1>
        <div class="mt-2 flex flex-wrap gap-1.5">@include('admin.orders._badges')</div>
    </header>

    @if ($order->isCancelled())
        <p class="toast bg-danger-soft text-on-danger-soft">Cette commande a été annulée. Contactez la boutique pour toute question.</p>
    @else
        {{-- Étape suivante, selon le moyen de paiement --}}
        <section class="section">
            @if ($order->isCashOnDelivery())
                <p class="text-lg font-bold">Dernière étape : envoyez votre commande</p>
                <p class="mt-1 text-text-muted">Vous paierez <strong class="text-text">{{ gnf($order->total) }}</strong> en espèces à la réception.</p>
            @elseif ($order->paymentMethod && $order->payment_status === 'unpaid')
                <p class="text-lg font-bold">Payez par {{ $order->paymentLabel() }}</p>
                <p class="mt-3 text-sm text-text-muted">Montant à envoyer</p>
                <p class="text-display font-bold">{{ gnf($order->total) }}</p>
                <p class="mt-3 text-sm text-text-muted">Au numéro</p>
                <p class="text-xl font-bold">{{ phone_display($order->paymentMethod->account_number) }}</p>
                @if ($order->paymentMethod->account_name)
                    <p class="text-sm text-text-muted">Nom affiché : {{ $order->paymentMethod->account_name }}</p>
                @endif
                <p class="mt-3 text-sm">Puis envoyez votre commande sur WhatsApp avec la capture de votre paiement.</p>
            @else
                <p class="text-lg font-bold">Merci pour votre commande !</p>
            @endif

            <a href="{{ route('invoice.whatsapp', $order) }}" class="btn btn-whatsapp btn-lg mt-4">
                <x-icon name="message" :size="20" /> {{ $order->whatsapp_sent_at ? 'Renvoyer sur WhatsApp' : 'Envoyer ma commande sur WhatsApp' }}
            </a>
        </section>
    @endif

    <section class="section">
        <h2 class="section-title">Détail</h2>
        @include('shop._summary', ['items' => $order->items, 'order' => $order])
    </section>

    <section class="section border-b-0 text-sm">
        <p><span class="text-text-muted">Nom :</span> {{ $order->customer_name }}</p>
        <p><span class="text-text-muted">Service :</span> {{ $order->delivery_method_name }}</p>
        @if ($order->address)<p><span class="text-text-muted">Adresse :</span> {{ $order->address }}</p>@endif
        <p class="mt-3 text-text-muted">Gardez ce lien : il montre l'état de votre commande.</p>
        <a href="{{ route('shop.show', $store) }}" class="mt-4 inline-block font-semibold text-accent">Retour à la boutique</a>
    </section>
</x-layouts.shop>
