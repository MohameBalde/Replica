<x-layouts.shop :store="$store" :title="$product->name.' · '.$store->name"
    :description="collect([($product->variants->count() > 1 ? 'À partir de ' : '').gnf($product->fromPrice()), \Illuminate\Support\Str::limit($product->description, 150)])->filter()->implode(' · ')"
    :image="preview_url($product->images->first()?->path)" wide>
    <header class="flex h-14 items-center gap-2 px-2 lg:h-16 lg:px-6">
        <a href="{{ route('shop.show', $store) }}" class="inline-flex min-h-tap min-w-tap items-center justify-center" aria-label="Retour à la boutique"><x-icon name="back" /></a>
        <span class="truncate font-semibold">{{ $store->name }}</span>
    </header>

    <x-flash />

    @php
        $soldOut = $product->isSoldOut();
        $available = $product->variants->first(fn ($v) => $v->isAvailable()) ?? $product->variants->first();
        $multi = $product->variants->count() > 1;
    @endphp

    {{-- Ordinateur : photos à gauche, choix et bouton à droite. --}}
    <div class="flex flex-1 flex-col lg:grid lg:grid-cols-2 lg:items-start lg:gap-10 lg:px-8 lg:pt-2 lg:pb-10">
    {{-- Photos : défilement horizontal natif (pas de carrousel JS). --}}
    <div class="relative bg-surface lg:overflow-hidden lg:rounded-lg {{ $soldOut ? 'opacity-50 grayscale' : '' }}">
        @if ($product->images->isNotEmpty())
            <ul class="flex snap-x snap-mandatory overflow-x-auto" aria-label="Photos">
                @foreach ($product->images as $image)
                    <li class="w-full shrink-0 snap-center">
                        <img src="{{ asset('storage/'.$image->path) }}" alt="{{ $product->name }}, photo {{ $loop->iteration }}"
                             class="aspect-square w-full object-cover" @unless ($loop->first) loading="lazy" @endunless>
                    </li>
                @endforeach
            </ul>
            @if ($product->images->count() > 1)
                <span class="badge badge-neutral absolute right-3 bottom-3">{{ $product->images->count() }} photos · glissez</span>
            @endif
        @else
            <div class="grid aspect-square place-items-center text-5xl font-bold text-text-muted" aria-hidden="true">{{ mb_strtoupper(mb_substr($product->name, 0, 2)) }}</div>
        @endif
    </div>

    <form method="POST" action="{{ route('shop.cart.add', $store) }}" class="flex flex-1 flex-col" id="add-form">
        @csrf
        <div class="p-4 lg:p-0">
            <h1 class="text-xl font-bold lg:text-display">{{ $product->name }}</h1>
            <p class="price mt-1 text-lg" id="price">{{ gnf($available->price) }}</p>

            @if ($multi)
                <fieldset class="mt-4">
                    <legend class="label">Choisissez</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($product->variants as $variant)
                            @php $ok = $variant->isAvailable(); @endphp
                            <label class="choice px-3 py-2 {{ $ok ? '' : 'cursor-not-allowed text-text-muted line-through opacity-60' }}">
                                <input type="radio" name="variant_id" value="{{ $variant->id }}" class="sr-only"
                                       data-price="{{ gnf($variant->price) }}" data-stock="{{ $variant->stock ?? '' }}"
                                       @checked($available->id === $variant->id) @disabled(! $ok)>
                                {{ $variant->name }}<span class="sr-only">{{ $ok ? '' : ' (épuisé)' }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
            @else
                <input type="hidden" name="variant_id" value="{{ $available->id }}">
            @endif

            @if (! $soldOut)
                <div class="mt-4 flex items-center gap-3">
                    <label for="quantity" class="label mb-0">Quantité</label>
                    <input class="input w-24 text-center" id="quantity" name="quantity" type="number" inputmode="numeric" min="1"
                           max="{{ $available->stock ?? \App\Services\Cart::MAX_QUANTITY }}" value="1">
                    <span class="badge badge-warning" id="low-stock" @unless ($available->stock !== null && $available->stock <= \App\Models\Product::LOW_STOCK) hidden @endunless>Plus que&nbsp;<span>{{ $available->stock }}</span></span>
                </div>
            @endif

            @if ($product->description)
                <div class="mt-5 whitespace-pre-line text-text-muted">{{ $product->description }}</div>
            @endif
        </div>

        <div class="sticky-bar mt-auto lg:static lg:mt-6 lg:p-0 lg:shadow-none">
            @if ($soldOut)
                <button class="btn btn-secondary btn-lg" type="button" disabled>Épuisé</button>
            @elseif (! $store->acceptsOrdersNow())
                <button class="btn btn-secondary btn-lg" type="button" disabled>Boutique fermée</button>
            @else
                <button class="btn btn-primary btn-lg" type="submit"><x-icon name="bag" :size="20" /> Ajouter au panier</button>
            @endif
            @if ($cartCount > 0)
                <a href="{{ route('shop.cart', $store) }}" class="mt-2 block text-center text-sm font-semibold text-accent">Voir le panier ({{ $cartCount }}) · {{ gnf($cartTotal) }}</a>
            @endif
        </div>
    </form>
    </div>

    @if ($multi)
        <script>
            document.getElementById('add-form').addEventListener('change', e => {
                if (e.target.name !== 'variant_id') return;
                document.getElementById('price').textContent = e.target.dataset.price;
                const stock = e.target.dataset.stock, low = document.getElementById('low-stock'), qty = document.getElementById('quantity');
                if (qty) qty.max = stock || {{ \App\Services\Cart::MAX_QUANTITY }};
                if (low) { low.hidden = !(stock !== '' && Number(stock) <= {{ \App\Models\Product::LOW_STOCK }}); low.querySelector('span').textContent = stock; }
            });
        </script>
    @endif
</x-layouts.shop>
