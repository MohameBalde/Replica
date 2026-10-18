<x-layouts.shop :store="$store" :title="'Commande #'.$order->number">
    <header class="px-4 pt-6 pb-2">
        <p class="text-sm font-semibold text-text-muted">{{ $store->name }}</p>
        <h1 class="text-xl font-bold">Commande #{{ $order->number }}</h1>
        <div class="mt-2 flex flex-wrap gap-1.5">@include('admin.orders._badges')</div>
    </header>

    <x-flash />

    @if ($order->isCancelled())
        <p class="toast bg-danger-soft text-on-danger-soft">Cette commande a été annulée. Contactez la boutique pour toute question.</p>
    @else
        {{-- Étape suivante, selon le moyen de paiement --}}
        <section class="section">
            @php $manual = $order->source === \App\Models\Order::MANUAL; // saisie par la boutique : rien à envoyer @endphp
            @if ($order->isCashOnDelivery() && $order->payment_status === 'unpaid')
                <p class="text-lg font-bold">{{ $manual ? 'Votre commande' : 'Dernière étape : envoyez votre commande' }}</p>
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
                <p class="mt-3 text-sm">Ensuite, ajoutez la capture de votre paiement ci-dessous{{ $manual ? '.' : ', puis envoyez la commande sur WhatsApp.' }}</p>
            @elseif ($order->payment_status === 'confirming')
                <p class="text-lg font-bold">Capture reçue 👍</p>
                <p class="mt-1 text-text-muted">{{ $store->name }} vérifie votre paiement de {{ gnf($order->total) }}.@unless ($manual) Envoyez la commande sur WhatsApp si ce n'est pas déjà fait.@endunless</p>
            @else
                <p class="text-lg font-bold">Merci pour votre commande !</p>
            @endif

            @if (! $order->isCashOnDelivery() && in_array($order->payment_status, ['unpaid', 'confirming'], true)
                && $order->payment_proofs_count < \App\Http\Controllers\Shop\InvoiceController::MAX_PROOFS)
                <form method="POST" action="{{ route('invoice.proof', $order) }}" enctype="multipart/form-data" class="mt-4 rounded-md bg-surface p-3"
                      onsubmit="this.querySelector('[type=submit]').disabled = true">
                    @csrf
                    <label class="label" for="proof">{{ $order->payment_proofs_count ? 'Envoyer une autre capture' : 'Capture de votre paiement' }}</label>
                    <input class="block w-full text-sm file:mr-3 file:min-h-tap file:rounded-md file:border file:border-border-input file:bg-bg file:px-3 file:font-semibold"
                           type="file" id="proof" name="proof" accept="image/*" required>
                    <label class="label mt-3" for="reference">N° de transaction <span class="font-normal text-text-muted">(facultatif)</span></label>
                    <input class="input" id="reference" name="reference" maxlength="60" placeholder="Ex. MP2610.1234.A56789">
                    @error('proof')<p class="error">{{ $message }}</p>@enderror
                    <button class="btn btn-secondary mt-3" type="submit">Envoyer la capture</button>
                </form>
            @endif

            @unless ($manual)
                <a href="{{ route('invoice.whatsapp', $order) }}" class="btn btn-whatsapp btn-lg mt-4">
                    <x-icon name="message" :size="20" /> {{ $order->whatsapp_sent_at ? 'Renvoyer sur WhatsApp' : 'Envoyer ma commande sur WhatsApp' }}
                </a>
            @endunless
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
        @include('admin.orders._answers', ['class' => ''])
        <p class="mt-3 text-text-muted">Gardez ce lien : il montre l'état de votre commande.</p>
        <a href="{{ route('shop.show', $store) }}" class="mt-4 inline-block font-semibold text-accent">Retour à la boutique</a>
    </section>
</x-layouts.shop>
