@if ($cartCount > 0)
    <div class="sticky-bar mt-auto">
        <a href="{{ route('shop.cart', $store) }}" class="btn btn-primary btn-lg">
            <x-icon name="bag" :size="20" /> Voir le panier ({{ $cartCount }}) · {{ gnf($cartTotal) }}
        </a>
    </div>
@endif
