<x-layouts.shop :store="$store" title="Finaliser ma commande">
    <header class="flex h-14 items-center gap-2 px-2">
        <a href="{{ route('shop.cart', $store) }}" class="inline-flex min-h-tap min-w-tap items-center justify-center" aria-label="Retour au panier"><x-icon name="back" /></a>
        <h1 class="text-lg font-semibold">Finaliser ma commande</h1>
    </header>

    <x-flash :inline="true" />
    @php $hours = $store->openingHours()->status(\Carbon\CarbonImmutable::now()); @endphp
    @if ($hours && ! $hours['open'] && $store->acceptsOrdersNow() && $hours['next'])
        <p class="toast bg-warning-soft text-on-warning-soft">{{ $store->name }} est fermée en ce moment. Vous pouvez commander : la boutique vous répondra {{ $hours['next'] }}.</p>
    @endif
    @error('cart')<p class="toast bg-danger-soft text-on-danger-soft" role="alert">{{ $message }}</p>@enderror

    <form method="POST" action="{{ route('shop.checkout.store', $store) }}" id="checkout" class="flex flex-1 flex-col" novalidate
          onsubmit="this.querySelector('[type=submit]').disabled = true">
        @csrf

        <section class="section">
            <div class="field">
                <label class="label" for="name">Votre nom</label>
                <input class="input @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required maxlength="120">
                @error('name')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field mb-0">
                <label class="label" for="phone">Numéro WhatsApp</label>
                <div class="input-group @error('phone') is-invalid @enderror">
                    <span class="input-addon">+224</span>
                    <input class="input" id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone') ? substr(old('phone'), -9) : '' }}" placeholder="620 00 00 00" autocomplete="tel" required>
                </div>
                @error('phone')<p class="error">{{ $message }}</p>@enderror
            </div>
            {{-- Questions de la boutique (Réglages > Commande). --}}
            @foreach ($store->checkoutQuestions() as $i => $q)
                <div class="field mt-4 mb-0">
                    <label class="label" for="answer-{{ $i }}">{{ $q['label'] }}@unless ($q['required']) <span class="font-normal text-text-muted">(facultatif)</span>@endunless</label>
                    <input class="input @error("answers.$i") is-invalid @enderror" id="answer-{{ $i }}" name="answers[{{ $i }}]" value="{{ old("answers.$i") }}" maxlength="255" @if ($q['required']) required @endif>
                    @error("answers.$i")<p class="error">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </section>

        <section class="section">
            <h2 class="section-title">Livraison</h2>
            <div class="space-y-2">
                @foreach ($deliveryMethods as $delivery)
                    <label class="choice">
                        <input type="radio" name="delivery_method_id" value="{{ $delivery->id }}" @checked((int) old('delivery_method_id', $deliveryMethods->first()->id) === $delivery->id)>
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold">{{ $delivery->name }}</span>
                            @if ($delivery->min_order > $subtotal)
                                <span class="block text-sm text-text-muted">Minimum {{ gnf($delivery->min_order) }}</span>
                            @endif
                        </span>
                        <span class="price">{{ $delivery->fee ? gnf($delivery->fee) : 'Gratuit' }}</span>
                    </label>
                @endforeach
            </div>
            @error('delivery_method_id')<p class="error">{{ $message }}</p>@enderror

            <div class="field mt-4 mb-0" id="address-field">
                <label class="label" for="address">Adresse de livraison</label>
                <input class="input @error('address') is-invalid @enderror" id="address" name="address" value="{{ old('address') }}" autocomplete="street-address" maxlength="255"
                       placeholder="Quartier, rue, repère (ex. près de la mosquée)">
                @error('address')<p class="error">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="section">
            <h2 class="section-title">Paiement</h2>
            <div class="space-y-2">
                @foreach ($paymentMethods as $payment)
                    <label class="choice" data-payment="{{ $payment->id }}">
                        <input type="radio" name="payment_method_id" value="{{ $payment->id }}" @checked((int) old('payment_method_id') === $payment->id)>
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold">{{ $payment->label() }}</span>
                            <span class="block text-sm text-text-muted">
                                {{ $payment->isCashOnDelivery() ? 'En espèces, à la réception' : 'Vous recevrez le numéro après la commande' }}
                            </span>
                        </span>
                    </label>
                @endforeach
            </div>
            <p class="help" id="cod-note" hidden></p>
            @error('payment_method_id')<p class="error">{{ $message }}</p>@enderror
        </section>

        <section class="section border-b-0">
            <div class="field mb-4">
                <label class="label" for="note">Message pour la boutique <span class="font-normal text-text-muted">(facultatif)</span></label>
                <textarea class="input" id="note" name="note" rows="2" maxlength="500">{{ old('note') }}</textarea>
            </div>
            <ul class="space-y-1 text-sm">
                @foreach ($lines as $line)
                    <li class="flex gap-3"><span class="w-8 text-text-muted">{{ $line['quantity'] }} ×</span><span class="flex-1">{{ $line['variant']->product->name }}{{ $line['variant']->name !== 'Standard' ? ' ('.$line['variant']->name.')' : '' }}</span><span class="whitespace-nowrap">{{ gnf($line['line_total']) }}</span></li>
                @endforeach
            </ul>
            <div class="mt-3 space-y-1 border-t border-border pt-3 text-sm">
                <div class="flex justify-between"><span>Articles</span><span>{{ gnf($subtotal) }}</span></div>
                <div class="flex justify-between"><span>Livraison</span><span id="fee"></span></div>
                <div class="flex justify-between border-t border-border pt-2 text-lg font-bold"><span>Total</span><span id="total">{{ gnf($subtotal) }}</span></div>
            </div>
        </section>

        <div class="sticky-bar mt-auto">
            <button class="btn btn-whatsapp btn-lg" type="submit"><x-icon name="message" :size="20" /> Valider ma commande</button>
            <p class="help text-center">Ensuite, vous envoyez la commande sur le WhatsApp de {{ $store->name }}. Vos coordonnées servent seulement à cette commande (<a class="underline" href="{{ route('legal.privacy') }}">confidentialité</a>).</p>
        </div>
    </form>

    {{-- Sans JavaScript, tout reste visible et le serveur vérifie. Avec, on masque ce qui n'est pas permis. --}}
    <script>
        (() => {
            const options = @json($options);
            const form = document.getElementById('checkout');
            const fmt = n => n ? new Intl.NumberFormat('fr-FR').format(n).replace(/ | /g, ' ') + ' GNF' : 'Gratuit';
            function refresh() {
                const d = form.querySelector('[name=delivery_method_id]:checked');
                if (!d) return;
                const o = options[d.value];
                document.getElementById('fee').textContent = fmt(o.fee);
                document.getElementById('total').textContent = fmt(o.total);
                document.getElementById('address-field').hidden = !o.needsAddress;
                form.querySelectorAll('[data-payment]').forEach(el => {
                    const allowed = o.payments.includes(Number(el.dataset.payment));
                    el.hidden = !allowed;
                    const input = el.querySelector('input');
                    input.disabled = !allowed;
                    if (!allowed) input.checked = false;
                });
                const visible = [...form.querySelectorAll('[data-payment]:not([hidden]) input')];
                if (visible.length && !visible.some(i => i.checked)) visible[0].checked = true;
                const note = document.getElementById('cod-note');
                note.textContent = o.codNote || '';
                note.hidden = !o.codNote;
            }
            form.addEventListener('change', e => { if (e.target.name === 'delivery_method_id') refresh(); });
            refresh();
        })();
    </script>
</x-layouts.shop>
