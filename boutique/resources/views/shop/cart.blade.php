<x-layouts.shop :store="$store" title="Mon panier">
    <header class="flex h-14 items-center gap-2 px-2">
        <a href="{{ route('shop.show', $store) }}" class="inline-flex min-h-tap min-w-tap items-center justify-center" aria-label="Continuer mes achats"><x-icon name="back" /></a>
        <h1 class="text-lg font-semibold">Mon panier</h1>
    </header>

    <x-flash />

    @if ($lines->isEmpty())
        <div class="px-4 py-16 text-center">
            <p class="text-lg font-semibold">Votre panier est vide.</p>
            <a href="{{ route('shop.show', $store) }}" class="btn btn-primary mt-6">Voir les produits</a>
        </div>
    @else
        <ul class="divide-y divide-border">
            @foreach ($lines as $line)
                @php $variant = $line['variant']; @endphp
                <li class="flex gap-3 px-4 py-3">
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ $variant->product->name }}</p>
                        @if ($variant->name !== 'Standard')<p class="text-sm text-text-muted">{{ $variant->name }}</p>@endif
                        <p class="text-sm text-text-muted">{{ gnf($variant->price) }} l'unité</p>
                        @unless ($variant->isAvailable($line['quantity']))
                            <p class="error mt-1">{{ $variant->stock > 0 ? 'Plus que '.$variant->stock.' en stock.' : 'Épuisé : retirez-le du panier.' }}</p>
                        @endunless
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <p class="price">{{ gnf($line['line_total']) }}</p>
                        <div class="flex items-center gap-1">
                            @foreach ([[-1, 'minus', 'Retirer un'], [1, 'plus', 'Ajouter un']] as [$step, $icon, $label])
                                @if ($step === 1 && ! $loop->first)
                                    <span class="w-8 text-center font-semibold" aria-live="polite">{{ $line['quantity'] }}</span>
                                @endif
                                <form method="POST" action="{{ route('shop.cart.update', $store) }}">
                                    @csrf @method('PATCH')
                                    <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                                    <input type="hidden" name="quantity" value="{{ $line['quantity'] + $step }}">
                                    <button class="grid size-tap place-items-center rounded-md border border-border-input" aria-label="{{ $label }}"
                                            @disabled($step === 1 && ! $variant->isAvailable($line['quantity'] + 1))><x-icon :name="$icon" :size="18" /></button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="sticky-bar mt-auto">
            <div class="mb-3 flex justify-between text-lg font-bold"><span>Sous-total</span><span>{{ gnf($subtotal) }}</span></div>
            @if ($closed = $store->closedNotice())
                <p class="mb-2 rounded-md bg-warning-soft px-3 py-2 text-sm text-on-warning-soft">{{ $closed }}</p>
                <button class="btn btn-secondary btn-lg" type="button" disabled>Commander</button>
            @else
                <a href="{{ route('shop.checkout', $store) }}" class="btn btn-primary btn-lg">Commander</a>
            @endif
        </div>
    @endif
</x-layouts.shop>
