<x-layouts.shop :store="$store" :image="preview_url($products->first(fn ($p) => $p->firstImage)?->firstImage->path)">
    <header class="flex items-center gap-3 px-4 pt-5 pb-3">
        <div class="grid size-12 shrink-0 place-items-center rounded-pill bg-accent-soft text-xl font-bold text-accent-strong" aria-hidden="true">
            {{ mb_strtoupper(mb_substr($store->name, 0, 1)) }}
        </div>
        <div class="min-w-0 flex-1">
            <h1 class="truncate text-lg font-semibold">{{ $store->name }}</h1>
            <p class="truncate text-sm text-text-muted">{{ $store->city }}{{ $store->description ? ' · '.$store->description : '' }}</p>
        </div>
        <span class="badge {{ $store->is_open ? 'badge-success' : 'badge-warning' }}">{{ $store->is_open ? 'Ouvert' : 'Fermé' }}</span>
    </header>

    @unless ($store->is_open)
        <p class="mx-4 mb-2 rounded-md bg-warning-soft px-3 py-2 text-sm text-on-warning-soft">La boutique est fermée pour le moment. Vous pouvez regarder les produits ; les commandes reprendront bientôt.</p>
    @endunless

    <x-flash />

    @if ($categories->isNotEmpty())
        <nav class="flex gap-5 overflow-x-auto border-b border-border px-4" aria-label="Catégories">
            <a href="{{ route('shop.show', $store) }}" @if (! $categoryId) aria-current="page" @endif
               class="whitespace-nowrap py-3 text-sm font-semibold {{ ! $categoryId ? 'text-accent shadow-[inset_0_-2px_0_var(--color-accent)]' : 'text-text-muted' }}">Tout</a>
            @foreach ($categories as $category)
                <a href="{{ route('shop.show', [$store, 'cat' => $category->id]) }}" @if ($categoryId === $category->id) aria-current="page" @endif
                   class="whitespace-nowrap py-3 text-sm font-semibold {{ $categoryId === $category->id ? 'text-accent shadow-[inset_0_-2px_0_var(--color-accent)]' : 'text-text-muted' }}">{{ $category->name }}</a>
            @endforeach
        </nav>
    @endif

    @if ($products->isEmpty())
        <div class="px-4 py-16 text-center">
            <p class="text-lg font-semibold">Cette boutique prépare ses produits.</p>
            <p class="mt-2 text-text-muted">Revenez bientôt !</p>
        </div>
    @else
        <ul class="grid grid-cols-2 gap-3 p-4 md:grid-cols-3">
            @foreach ($products as $product)
                @php $stock = $product->totalStock(); $soldOut = $product->isSoldOut(); @endphp
                <li>
                    <a href="{{ route('shop.product', [$store, $product]) }}" class="card block h-full overflow-hidden">
                        <div class="grid aspect-square place-items-center bg-surface text-3xl font-bold text-text-muted {{ $soldOut ? 'opacity-50 grayscale' : '' }}">
                            @if ($product->firstImage)
                                <img src="{{ asset('storage/'.$product->firstImage->thumb_path) }}" alt="{{ $product->name }}" loading="lazy" class="size-full object-cover">
                            @else
                                <span aria-hidden="true">{{ mb_strtoupper(mb_substr($product->name, 0, 2)) }}</span>
                            @endif
                        </div>
                        <div class="p-2.5">
                            <p class="line-clamp-2 text-sm">{{ $product->name }}</p>
                            <p class="price mt-1">{{ $product->variants->min('price') !== $product->variants->max('price') ? 'Dès ' : '' }}{{ gnf($product->fromPrice()) }}</p>
                            @if ($soldOut)
                                <span class="badge badge-neutral mt-1.5">Épuisé</span>
                            @elseif ($stock !== null && $stock <= 3)
                                <span class="badge badge-warning mt-1.5">Plus que {{ $stock }}</span>
                            @endif
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    @include('shop._cart_bar')
</x-layouts.shop>
