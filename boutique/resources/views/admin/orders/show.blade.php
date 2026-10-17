<x-layouts.admin :title="'Commande #'.$order->number" :back="route('admin.orders.index')">
    <section class="section">
        <div class="flex flex-wrap gap-1.5">@include('admin.orders._badges')</div>
        <p class="mt-2 text-sm text-text-muted">
            Reçue le {{ $order->created_at->timezone('Africa/Conakry')->format('d/m/Y à H:i') }}
            · via {{ \App\Models\Order::SOURCES[$order->source] ?? $order->source }}{{ $order->campaign ? ' ('.$order->campaign.')' : '' }}
        </p>
        @unless ($order->whatsapp_sent_at)
            <p class="mt-2 text-sm text-text-muted">Le client n'a pas encore envoyé le message WhatsApp.</p>
        @endunless
    </section>

    <section class="section">
        <h2 class="section-title">Client</h2>
        <p class="font-semibold">
            @if ($order->customer)
                <a class="text-accent" href="{{ route('admin.customers.show', $order->customer->id) }}">{{ $order->customer_name }}</a>
                @if ($order->customer->cash_on_delivery_blocked)<span class="badge badge-danger ml-1">Paiement avant livraison</span>@endif
            @else
                {{ $order->customer_name }}
            @endif
        </p>
        <p class="text-text-muted">{{ phone_display($order->customer_phone) }}</p>
        <p class="mt-1">{{ $order->delivery_method_name }}@if ($order->address) · {{ $order->address }}@endif</p>
        @if ($order->note)<p class="mt-2 rounded-md bg-surface p-3 text-sm">« {{ $order->note }} »</p>@endif
        <a href="{{ $writeToCustomerUrl }}" class="btn btn-whatsapp mt-4" target="_blank" rel="noopener"><x-icon name="message" :size="20" /> Écrire au client</a>
    </section>

    @if ($order->paymentProofs->isNotEmpty())
        <section class="section">
            <h2 class="section-title">Captures de paiement</h2>
            <p class="mb-3 rounded-md bg-warning-soft p-3 text-sm text-on-warning-soft">Une capture peut être fausse : vérifiez sur votre téléphone {{ $order->paymentLabel() }} que {{ gnf($order->total) }} sont bien arrivés.</p>
            <ul class="grid grid-cols-2 gap-3">
                @foreach ($order->paymentProofs as $proof)
                    <li>
                        <a href="{{ route('admin.orders.proof', [$order->id, $proof->id]) }}" target="_blank" rel="noopener">
                            <img src="{{ route('admin.orders.proof', [$order->id, $proof->id]) }}" alt="Capture de paiement du {{ $proof->created_at->format('d/m H:i') }}"
                                 class="aspect-[3/4] w-full rounded-md border border-border object-cover" loading="lazy">
                        </a>
                        <p class="mt-1 text-xs text-text-muted">{{ $proof->created_at->timezone('Africa/Conakry')->format('d/m H:i') }}@if ($proof->reference) · n° {{ $proof->reference }}@endif</p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="section">
        <h2 class="section-title">Articles</h2>
        @include('shop._summary', ['items' => $order->items, 'order' => $order])
    </section>

    @unless ($order->isCancelled())
        <section class="section">
            <h2 class="section-title">Mettre à jour</h2>
            @if ($order->isCashOnDelivery())
                <p class="mb-3 rounded-md bg-accent-soft p-3 text-sm text-accent-strong">Paiement à la livraison : marquez « Payée » quand vous avez encaissé {{ gnf($order->total) }}.</p>
            @else
                <p class="mb-3 rounded-md bg-warning-soft p-3 text-sm text-on-warning-soft">Vérifiez sur votre téléphone que l'argent est bien arrivé avant de marquer « Payée ». Une capture d'écran peut être fausse.</p>
            @endif
            <form method="POST" action="{{ route('admin.orders.statuses', $order->id) }}">
                @csrf @method('PATCH')
                @foreach ([['status', 'Commande', array_diff_key(\App\Models\Order::STATUSES, ['cancelled' => 1])], ['payment_status', 'Paiement', \App\Models\Order::PAYMENT_STATUSES], ['fulfillment_status', 'Livraison', \App\Models\Order::FULFILLMENT_STATUSES]] as [$field, $label, $options])
                    <div class="field">
                        <label class="label" for="{{ $field }}">{{ $label }}</label>
                        <select class="input" id="{{ $field }}" name="{{ $field }}">
                            @foreach ($options as $value => $text)
                                <option value="{{ $value }}" @selected($order->$field === $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
                <button class="btn btn-primary" type="submit">Enregistrer</button>
            </form>
            <form method="POST" action="{{ route('admin.orders.cancel', $order->id) }}" class="mt-3" onsubmit="return confirm('Annuler la commande #{{ $order->number }} ? Le stock sera remis.')">
                @csrf
                <button class="btn btn-danger" type="submit">Annuler la commande</button>
            </form>
        </section>
    @endunless
</x-layouts.admin>
