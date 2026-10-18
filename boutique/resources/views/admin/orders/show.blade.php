<x-layouts.admin :title="'Commande #'.$order->number" :back="route('admin.orders.index')">
    <section class="section">
        <div class="flex flex-wrap gap-1.5">@include('admin.orders._badges')</div>
        <p class="mt-2 text-sm text-text-muted">
            Reçue le {{ $order->created_at->timezone('Africa/Conakry')->format('d/m/Y à H:i') }}
            @if ($order->sourceLabel())· {{ $order->sourceLabel() }}{{ $order->campaign ? ' ('.$order->campaign.')' : '' }}@endif
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
        @if ($order->internal_note)<p class="mt-2 rounded-md bg-warning-soft p-3 text-sm text-on-warning-soft"><span class="font-semibold">Note interne :</span> {{ $order->internal_note }}</p>@endif
    </section>

    {{-- Messages prêts : un clic ouvre WhatsApp avec le texte, le commerçant n'a plus qu'à envoyer. --}}
    <section class="section">
        <h2 class="section-title">Écrire au client sur WhatsApp</h2>
        <ul class="space-y-2">
            @foreach ($messages as $key => $m)
                <li>
                    <a href="{{ $m['url'] }}" target="_blank" rel="noopener" data-message="{{ $key }}"
                       class="flex min-h-tap items-center gap-3 rounded-md border border-border px-3 py-2.5 hover:bg-surface">
                        <span class="grid size-9 shrink-0 place-items-center rounded-pill bg-whatsapp text-on-whatsapp"><x-icon name="message" :size="18" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold">{{ $m['label'] }}</span>
                            <span class="line-clamp-2 text-sm text-text-muted">{{ \Illuminate\Support\Str::after($m['text'], "\n") }}</span>
                        </span>
                        <x-icon name="external" :size="16" class="shrink-0 text-text-muted" />
                    </a>
                </li>
            @endforeach
        </ul>
        <div class="mt-3 flex flex-wrap gap-x-5">
            <a href="{{ $writeToCustomerUrl }}" class="inline-flex min-h-tap items-center text-sm font-semibold text-accent" target="_blank" rel="noopener">Message libre →</a>
            <a href="{{ route('admin.orders.print', $order->id) }}" class="inline-flex min-h-tap items-center text-sm font-semibold text-accent" target="_blank" rel="noopener">Imprimer la facture (ou PDF) →</a>
        </div>
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
