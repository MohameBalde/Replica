@php
    $rows = array_values(old('items', [['variant_id' => '', 'quantity' => 1, 'price' => ''], ['variant_id' => '', 'quantity' => 1, 'price' => '']]));
    $paymentLabels = \App\Models\PaymentMethod::LABELS;
@endphp
<x-layouts.admin title="Nouvelle commande" :back="route('admin.orders.index')">
    <form id="manual-order" method="POST" action="{{ route('admin.orders.store') }}" class="p-4" novalidate
          onsubmit="this.querySelector('[type=submit]').disabled = true">
        @csrf
        <p class="help mt-0 mb-4">Pour une commande reçue par téléphone, en message ou vendue au comptoir. Elle compte dans vos chiffres et le stock est retiré.</p>

        @if ($products->isEmpty())
            <div class="rounded-md bg-surface p-4 text-center">
                <p class="font-semibold">Ajoutez d'abord un produit.</p>
                <a href="{{ route('admin.products.create') }}" class="btn btn-primary mt-3">Ajouter un produit</a>
            </div>
        @else
            <h2 class="section-title">Client</h2>
            <div class="field">
                <label class="label" for="name">Nom</label>
                <input class="input @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="off">
                @error('name')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label class="label" for="phone">Numéro WhatsApp</label>
                <input class="input @error('phone') is-invalid @enderror" id="phone" name="phone" value="{{ old('phone') }}" required inputmode="tel" placeholder="620 00 00 00" autocomplete="off">
                @error('phone')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label class="label" for="address">Adresse de livraison <span class="font-normal text-text-muted">(si livraison)</span></label>
                <input class="input @error('address') is-invalid @enderror" id="address" name="address" value="{{ old('address') }}" maxlength="255" placeholder="Ex. Kipé, près de la pharmacie">
                @error('address')<p class="error">{{ $message }}</p>@enderror
            </div>

            <h2 class="section-title mt-6">Articles</h2>
            @error('items')<p class="error mb-3">{{ $message }}</p>@enderror
            <div id="item-rows" class="space-y-3">
                @foreach ($rows as $i => $row)
                    <div class="rounded-md border border-border p-3" data-item>
                        <label class="label" for="i{{ $i }}-variant">Article</label>
                        <select class="input" id="i{{ $i }}-variant" name="items[{{ $i }}][variant_id]" data-variant>
                            <option value="">Choisir un article…</option>
                            @foreach ($products as $product)
                                @foreach ($product->variants as $variant)
                                    @php
                                        $label = $product->name.($variant->name !== 'Standard' ? ' ('.$variant->name.')' : '');
                                        $stock = $variant->stock === null ? '' : ($variant->stock > 0 ? ' · '.$variant->stock.' en stock' : ' · épuisé');
                                    @endphp
                                    <option value="{{ $variant->id }}" data-price="{{ $variant->price }}" @selected((string) ($row['variant_id'] ?? '') === (string) $variant->id)
                                            @disabled($variant->stock === 0)>{{ $label }} · {{ gnf($variant->price) }}{{ $stock }}</option>
                                @endforeach
                            @endforeach
                        </select>
                        <div class="mt-2 grid grid-cols-[6rem_1fr] gap-2">
                            <div>
                                <label class="label" for="i{{ $i }}-qty">Quantité</label>
                                <input class="input" id="i{{ $i }}-qty" name="items[{{ $i }}][quantity]" type="number" min="1" max="999" inputmode="numeric" value="{{ $row['quantity'] ?? 1 }}" data-qty>
                            </div>
                            <div>
                                <label class="label" for="i{{ $i }}-price">Prix unitaire <span class="font-normal text-text-muted">(si négocié)</span></label>
                                <div class="input-group">
                                    <input class="input" id="i{{ $i }}-price" name="items[{{ $i }}][price]" inputmode="numeric" placeholder="Catalogue" value="{{ $row['price'] ?? '' }}" data-own-price>
                                    <span class="input-addon">GNF</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
            <button type="button" class="btn btn-secondary mt-3" data-add-item hidden><x-icon name="plus" :size="18" /> Ajouter un article</button>
            <p class="help">Article vide = ligne ignorée.</p>

            <h2 class="section-title mt-6">Livraison et paiement</h2>
            <div class="field">
                <label class="label" for="delivery_method_id">Livraison</label>
                <select class="input @error('delivery_method_id') is-invalid @enderror" id="delivery_method_id" name="delivery_method_id" data-delivery>
                    @foreach ($deliveryMethods as $method)
                        <option value="{{ $method->id }}" data-fee="{{ $method->fee }}" @selected((string) old('delivery_method_id') === (string) $method->id)>
                            {{ $method->name }} · {{ $method->fee ? gnf($method->fee) : 'Gratuit' }}{{ $method->is_active ? '' : ' (masqué en ligne)' }}
                        </option>
                    @endforeach
                </select>
                @error('delivery_method_id')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label class="label" for="payment_method_id">Paiement</label>
                <select class="input @error('payment_method_id') is-invalid @enderror" id="payment_method_id" name="payment_method_id">
                    @foreach ($paymentMethods as $method)
                        <option value="{{ $method->id }}" @selected((string) old('payment_method_id') === (string) $method->id)>
                            {{ $paymentLabels[$method->type] ?? $method->type }}{{ $method->isComplete() ? '' : ' (numéro à ajouter)' }}{{ $method->is_active ? '' : ' (masqué en ligne)' }}
                        </option>
                    @endforeach
                </select>
                @error('payment_method_id')<p class="error">{{ $message }}</p>@enderror
            </div>
            <label class="card mb-4 flex items-center gap-3 p-4">
                <span class="flex-1">
                    <span class="block font-semibold">Déjà payée</span>
                    <span class="text-sm text-text-muted">L'argent est reçu (espèces, Orange Money vérifié…).</span>
                </span>
                <span class="switch"><input type="checkbox" role="switch" name="paid" value="1" @checked(old('paid'))><span></span></span>
            </label>

            <fieldset class="field">
                <legend class="label">État de la commande</legend>
                <div class="space-y-2">
                    @foreach (\App\Http\Controllers\Admin\ManualOrderController::STATUSES as $value => $text)
                        <label class="flex min-h-tap items-center gap-3 rounded-md border border-border px-3 has-checked:border-accent has-checked:bg-accent-soft/40">
                            <input type="radio" name="status" value="{{ $value }}" class="size-5 accent-accent" @checked(old('status', 'confirmed') === $value)>
                            <span>{{ $text }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="field">
                <label class="label" for="internal_note">Note <span class="font-normal text-text-muted">(visible par vous seul)</span></label>
                <textarea class="input" id="internal_note" name="internal_note" rows="2" maxlength="500" placeholder="Ex. livrer après 17 h">{{ old('internal_note') }}</textarea>
            </div>

            {{-- Téléphone : au-dessus du menu du bas (h-16). --}}
            <div class="sticky-bar -mx-4 bottom-16 flex items-center gap-3 lg:bottom-0">
                <p class="min-w-0 flex-1 text-sm">Total<br><strong class="text-lg whitespace-nowrap" data-total>—</strong></p>
                <button class="btn btn-primary btn-lg w-auto px-6" type="submit">Enregistrer</button>
            </div>
        @endif
    </form>

    @if ($products->isNotEmpty())
        <script>
            (() => {
                const form = document.getElementById('manual-order');
                const rows = document.getElementById('item-rows');
                const add = form.querySelector('[data-add-item]');
                const digits = v => Number(String(v).replace(/\D+/g, '')) || 0;
                const fmt = n => new Intl.NumberFormat('fr-FR').format(n).replace(/\s/g, ' ') + ' GNF';
                function total() {
                    let sum = 0;
                    rows.querySelectorAll('[data-item]').forEach(row => {
                        const opt = row.querySelector('[data-variant]').selectedOptions[0];
                        if (!opt || !opt.value) return;
                        const own = row.querySelector('[data-own-price]').value.trim();
                        const price = own === '' ? Number(opt.dataset.price) : digits(own);
                        sum += price * Math.max(1, digits(row.querySelector('[data-qty]').value) || 1);
                    });
                    const fee = Number(form.querySelector('[data-delivery]').selectedOptions[0]?.dataset.fee || 0);
                    form.querySelector('[data-total]').textContent = sum ? fmt(sum + fee) : '—';
                }
                // Une ligne de plus : copie de la dernière, vidée et renumérotée.
                add.hidden = false;
                add.addEventListener('click', () => {
                    const n = rows.children.length;
                    if (n >= {{ \App\Http\Controllers\Admin\ManualOrderController::MAX_LINES }}) return;
                    const copy = rows.lastElementChild.cloneNode(true);
                    copy.querySelectorAll('[name], [id], [for]').forEach(el => {
                        ['name', 'id', 'for'].forEach(a => { if (el.hasAttribute(a)) el.setAttribute(a, el.getAttribute(a).replace(/\d+/, n)); });
                    });
                    copy.querySelector('[data-variant]').value = '';
                    copy.querySelector('[data-qty]').value = 1;
                    copy.querySelector('[data-own-price]').value = '';
                    rows.appendChild(copy);
                    copy.querySelector('[data-variant]').focus();
                });
                form.addEventListener('input', total);
                form.addEventListener('change', total);
                total();
            })();
        </script>
    @endif
</x-layouts.admin>
