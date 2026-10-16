<nav class="flex gap-5 overflow-x-auto border-b border-border px-4" aria-label="Réglages">
    @foreach ([['admin.settings.store', 'admin.settings.*', 'Boutique'], ['admin.delivery.index', 'admin.delivery.*', 'Livraison'], ['admin.payments.edit', 'admin.payments.*', 'Paiements']] as [$route, $pattern, $label])
        @php $on = request()->routeIs($pattern); @endphp
        <a href="{{ route($route) }}" @if ($on) aria-current="page" @endif
           class="whitespace-nowrap py-3 text-sm font-semibold {{ $on ? 'text-accent shadow-[inset_0_-2px_0_var(--color-accent)]' : 'text-text-muted' }}">{{ $label }}</a>
    @endforeach
</nav>
