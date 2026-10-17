@php $codOn = $cod && $cod->is_active; @endphp
<x-layouts.admin title="Réglages">
    @include('admin.settings._tabs')

    <div class="space-y-3 p-4">
        @error('delivery')<p class="toast mx-0 bg-danger-soft text-on-danger-soft" role="alert">{{ $message }}</p>@enderror

        @unless ($codOn)
            <p class="rounded-md bg-neutral-soft p-3 text-sm text-on-neutral-soft">
                Le paiement à la livraison est bloqué pour toute la boutique. <a class="font-semibold text-accent" href="{{ route('admin.payments.edit') }}">Le réactiver</a>
            </p>
        @endunless

        @foreach ($methods as $method)
            <details class="card" @if ($errors->any() && old('_method_id') == $method->id) open @endif>
                <summary class="flex min-h-tap cursor-pointer items-center gap-3 p-4">
                    <span class="min-w-0 flex-1">
                        <span class="block truncate font-bold {{ $method->is_active ? '' : 'text-text-muted line-through' }}">{{ $method->name }}</span>
                        <span class="block text-sm text-text-muted">
                            {{ $method->fee ? gnf($method->fee) : 'Gratuit' }}
                            @if ($method->min_order) · min. {{ gnf($method->min_order) }} @endif
                        </span>
                    </span>
                    @if (! $method->is_active)
                        <span class="badge badge-neutral">Désactivé</span>
                    @elseif ($codOn && $method->allows_cash_on_delivery)
                        <span class="badge badge-accent">À la livraison ✓</span>
                    @elseif ($codOn)
                        <span class="badge badge-neutral">Payer avant</span>
                    @endif
                </summary>
                <div class="border-t border-border p-4">
                    @include('admin.settings._delivery_form', ['method' => $method, 'action' => route('admin.delivery.update', $method->id), 'verb' => 'PUT'])
                </div>
            </details>
        @endforeach

        <details class="card" @if ($errors->any() && ! old('_method_id')) open @endif>
            <summary class="flex min-h-tap cursor-pointer items-center gap-2 p-4 font-semibold text-accent">
                <x-icon name="plus" :size="20" /> Ajouter un mode de livraison
            </summary>
            <div class="border-t border-border p-4">
                @if ($suggestions->isNotEmpty())
                    <p class="help mt-0 mb-2">Idées :</p>
                    <div class="mb-4 flex flex-wrap gap-2">
                        @foreach ($suggestions as $s)
                            <button type="button" class="badge badge-neutral min-h-8 px-3" onclick="document.getElementById('new-name').value = 'Livraison {{ $s }}'">{{ $s }}</button>
                        @endforeach
                    </div>
                @endif
                @include('admin.settings._delivery_form', ['method' => new \App\Models\DeliveryMethod(['type' => 'delivery', 'allows_cash_on_delivery' => true, 'is_active' => true]), 'action' => route('admin.delivery.store'), 'verb' => 'POST'])
            </div>
        </details>
    </div>
</x-layouts.admin>
