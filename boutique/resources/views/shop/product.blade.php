<x-layouts.shop :store="$store" :title="$product->name.' · '.$store->name" :description="\Illuminate\Support\Str::limit($product->description, 150)">
    <header class="flex h-14 items-center gap-2 px-2">
        <a href="{{ route('shop.show', $store) }}" class="inline-flex min-h-tap min-w-tap items-center justify-center" aria-label="Retour à la boutique"><x-icon name="back" /></a>
        <span class="truncate font-semibold">{{ $store->name }}</span>
    </header>

    <x-flash />

    @php $soldOut = $product->isSoldOut(); @endphp
    <div class="grid aspect-square place-items-center bg-surface text-5xl font-bold text-text-muted {{ $soldOut ? 'opacity-50 grayscale' : '' }}">
        @if ($product->images->isNotEmpty())
            <img src="{{ asset('storage/'.$product->images->first()->path) }}" alt="{{ $product->name }}" class="size-full object-cover">
        @else
            <span aria-hidden="true">{{ mb_strtoupper(mb_substr($product->name, 0, 2)) }}</span>
        @endif
    </div>

    <form method="POST" action="{{ route('shop.cart.add', $store) }}" class="flex flex-1 flex-col">
        @csrf
        <div class="p-4">
            <h1 class="text-xl font-bold">{{ $product->name }}</h1>
            <p class="price mt-1 text-lg">{{ gnf($product->fromPrice()) }}</p>

            @if ($product->variants->count() > 1)
                <fieldset class="mt-4">
                    <legend class="label">Choix</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($product->variants as $variant)
                            <label class="choice px-3 py-2 {{ $variant->isAvailable() ? '' : 'pointer-events-none text-text-muted line-through opacity-60' }}">
                                <input type="radio" name="variant_id" value="{{ $variant->id }}" class="sr-only" @checked($loop->first) @disabled(! $variant->isAvailable())>
                                {{ $variant->name }}
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @else
                <input type="hidden" name="variant_id" value="{{ $product->variants->first()->id }}">
            @endif

            @if (! $soldOut)
                @php $max = $product->track_stock ? min(\App\Services\Cart::MAX_QUANTITY, max(1, $product->totalStock())) : \App\Services\Cart::MAX_QUANTITY; @endphp
                <div class="mt-4 flex items-center gap-3">
                    <label for="quantity" class="label mb-0">Quantité</label>
                    <input class="input w-24 text-center" id="quantity" name="quantity" type="number" inputmode="numeric" min="1" max="{{ $max }}" value="1">
                    @if ($product->track_stock && $product->totalStock() <= 3)
                        <span class="badge badge-warning">Plus que {{ $product->totalStock() }}</span>
                    @endif
                </div>
            @endif

            @if ($product->description)
                <div class="mt-5 text-text-muted whitespace-pre-line">{{ $product->description }}</div>
            @endif
        </div>

        <div class="sticky-bar mt-auto">
            @if ($soldOut)
                <button class="btn btn-secondary btn-lg" type="button" disabled>Épuisé</button>
            @elseif (! $store->is_open)
                <button class="btn btn-secondary btn-lg" type="button" disabled>Boutique fermée</button>
            @else
                <button class="btn btn-primary btn-lg" type="submit"><x-icon name="bag" :size="20" /> Ajouter au panier</button>
            @endif
            @if ($cartCount > 0)
                <a href="{{ route('shop.cart', $store) }}" class="mt-2 block text-center text-sm font-semibold text-accent">Voir le panier ({{ $cartCount }}) · {{ gnf($cartTotal) }}</a>
            @endif
        </div>
    </form>
</x-layouts.shop>
