<x-layouts.admin title="Moyens de paiement">
    <div class="space-y-3 p-4">
        @error('is_active')<p class="toast mx-0 bg-danger-soft text-on-danger-soft" role="alert">{{ $message }}</p>@enderror

        {{-- Paiement à la livraison : le commerçant l'active ou le bloque, avec un plafond optionnel. --}}
        @if ($cod)
            <form method="POST" action="{{ route('admin.payments.cod') }}" class="card p-4" id="cod-form">
                @csrf @method('PUT')
                <div class="flex items-start gap-3">
                    <span class="badge badge-accent mt-0.5 shrink-0 px-2 py-2 text-xs">GNF</span>
                    <label for="cod-active" class="min-w-0 flex-1 cursor-pointer">
                        <span class="block font-bold">Paiement à la livraison</span>
                        <span class="block text-sm text-text-muted">Le client paie en espèces quand il reçoit sa commande.</span>
                    </label>
                    <span class="switch">
                        <input type="checkbox" role="switch" id="cod-active" name="is_active" value="1" @checked(old('is_active', $cod->is_active))
                               onchange="document.getElementById('cod-details').hidden = !this.checked; this.form.requestSubmit()">
                        <span></span>
                    </span>
                </div>

                <div id="cod-details" @unless ($cod->is_active) hidden @endunless class="mt-3 border-t border-border pt-3">
                    <label class="flex items-center gap-2.5 text-sm font-semibold">
                        <input type="checkbox" name="limit" value="1" class="size-5 accent-accent" @checked(old('limit', $cod->max_order_total !== null))
                               onchange="document.getElementById('cod-cap').hidden = !this.checked">
                        Limiter aux commandes jusqu'à…
                    </label>
                    <div id="cod-cap" @if ($cod->max_order_total === null && ! old('limit')) hidden @endif class="mt-2.5">
                        <div class="input-group @error('max_order_total') is-invalid @enderror">
                            <label for="max_order_total" class="sr-only">Montant maximum</label>
                            <input class="input" id="max_order_total" name="max_order_total" inputmode="numeric" placeholder="500 000"
                                   value="{{ old('max_order_total', $cod->max_order_total ? gnf($cod->max_order_total, false) : '') }}">
                            <span class="input-addon">GNF</span>
                        </div>
                        @error('max_order_total')<p class="error">{{ $message }}</p>@enderror
                        <p class="help">Au-delà, le client devra payer avant (Orange Money, MTN…).</p>
                    </div>
                    @php $codDeliveries = $deliveryMethods->where('allows_cash_on_delivery', true)->pluck('name'); @endphp
                    <p class="help">Livraisons concernées : {{ $codDeliveries->isEmpty() ? 'aucune' : $codDeliveries->join(', ') }}.</p>
                    <button class="btn btn-secondary mt-3" type="submit">Enregistrer</button>
                </div>
                @unless ($cod->is_active)
                    <p class="help">Bloqué : vos clients devront payer avant la livraison.</p>
                @endunless
            </form>
        @endif

        {{-- Mobile money --}}
        @foreach ($methods->whereIn('type', ['orange_money', 'mtn_momo']) as $method)
            <form method="POST" action="{{ route('admin.payments.mobile', $method->type) }}" class="card p-4">
                @csrf @method('PUT')
                <div class="flex items-start gap-3">
                    <span class="badge {{ $method->type === 'orange_money' ? 'badge-accent' : 'badge-warning' }} mt-0.5 shrink-0 px-2 py-2 text-xs">{{ $method->type === 'orange_money' ? 'OM' : 'MTN' }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="font-bold">{{ $method->label() }}</p>
                        @if ($method->isComplete())
                            <p class="text-sm text-text-muted">{{ phone_display($method->account_number) }}{{ $method->account_name ? ' · '.$method->account_name : '' }}</p>
                        @else
                            <span class="badge badge-warning mt-1">Numéro à ajouter</span>
                        @endif
                    </div>
                    <span class="switch">
                        <input type="checkbox" role="switch" name="is_active" value="1" aria-label="Activer {{ $method->label() }}" @checked($method->is_active)>
                        <span></span>
                    </span>
                </div>
                <div class="mt-3 border-t border-border pt-3">
                    <div class="field">
                        <label class="label" for="{{ $method->type }}-number">Numéro marchand</label>
                        <div class="input-group @error($method->type.'.account_number') is-invalid @enderror">
                            <span class="input-addon">+224</span>
                            <input class="input" id="{{ $method->type }}-number" name="account_number" type="tel" inputmode="tel"
                                   value="{{ $method->account_number ? substr(phone_display($method->account_number), 5) : '' }}">
                        </div>
                        @error($method->type.'.account_number')<p class="error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label class="label" for="{{ $method->type }}-name">Nom affiché par l'opérateur</label>
                        <input class="input" id="{{ $method->type }}-name" name="account_name" value="{{ $method->account_name }}" maxlength="120" aria-describedby="{{ $method->type }}-name-help">
                        <p class="help" id="{{ $method->type }}-name-help">Le client le verra au moment de payer : ça le rassure.</p>
                    </div>
                    <button class="btn btn-secondary" type="submit">Enregistrer</button>
                </div>
            </form>
        @endforeach
    </div>
</x-layouts.admin>
