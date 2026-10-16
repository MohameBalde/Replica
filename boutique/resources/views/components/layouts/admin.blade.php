@props(['title' => null, 'back' => null])
<x-layouts.base :title="$title" class="min-h-dvh">
    <div class="mx-auto flex min-h-dvh max-w-admin flex-col bg-bg">
        <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-border bg-bg px-4">
            @if ($back)
                <a href="{{ $back }}" class="-ml-2 inline-flex min-h-tap min-w-tap items-center justify-center" aria-label="Retour">
                    <x-icon name="back" />
                </a>
            @endif
            <h1 class="flex-1 truncate text-lg font-semibold">{{ $title }}</h1>
            @isset($currentStore)
                <a href="{{ route('shop.show', $currentStore) }}" class="btn-ghost inline-flex items-center gap-1 text-sm font-semibold" target="_blank" rel="noopener">
                    Ma boutique <x-icon name="external" :size="16" />
                </a>
            @endisset
        </header>

        @isset($currentStore)
            {{-- Rappel avant la fin d'une formule payante : jamais de prélèvement, donc on prévient. --}}
            @php $left = $currentStore->planDaysLeft(); @endphp
            @if ($left !== null && $left <= config('wanllan.reminder_days'))
                <a href="{{ route('admin.subscription') }}" class="toast block bg-warning-soft text-on-warning-soft">
                    Votre formule {{ \App\Support\Plans::get($currentStore->activePlan())['name'] }} se termine dans {{ $left }} jour{{ $left > 1 ? 's' : '' }}. Renouveler →
                </a>
            @endif
        @endisset

        <x-flash />

        <main class="flex-1 pb-24">{{ $slot }}</main>

        @isset($currentStore)
            <nav aria-label="Menu" class="fixed inset-x-0 bottom-0 z-30 mx-auto flex h-16 max-w-admin border-t border-border bg-bg pb-[env(safe-area-inset-bottom)]">
                @php
                    $items = [
                        ['admin.orders.index', ['admin.orders.*', 'admin.customers.*'], 'inbox', 'Commandes'],
                        ['admin.products.index', ['admin.products.*', 'admin.categories.*'], 'tag', 'Produits'],
                        ['admin.share', ['admin.share*'], 'share', 'Partager'],
                        ['admin.settings.store', ['admin.settings.*', 'admin.delivery.*', 'admin.payments.*', 'admin.subscription'], 'settings', 'Réglages'],
                    ];
                @endphp
                @foreach ($items as [$route, $patterns, $icon, $label])
                    @php $active = request()->routeIs(...$patterns); @endphp
                    <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
                       class="flex flex-1 flex-col items-center justify-center gap-1 text-xs font-semibold {{ $active ? 'text-accent' : 'text-text-muted' }}">
                        <x-icon :name="$icon" />{{ $label }}
                    </a>
                @endforeach
            </nav>
        @endisset
    </div>
</x-layouts.base>
