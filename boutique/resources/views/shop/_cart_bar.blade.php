@if ($cartCount > 0)
    <div class="sticky-bar mt-auto lg:flex lg:justify-end lg:px-8">
        <a href="{{ route('shop.cart', $store) }}" class="btn btn-primary btn-lg lg:w-auto lg:px-8">
            <x-icon name="bag" :size="20" /> Voir le panier ({{ $cartCount }}) · {{ gnf($cartTotal) }}
        </a>
    </div>
@endif
