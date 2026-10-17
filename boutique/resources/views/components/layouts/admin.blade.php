@props(['title' => null, 'back' => null, 'wide' => false, 'nav' => true])
@php
    $user = auth()->user();
    // Menu de la boutique : sur ses propres pages, et depuis l'espace Gestion pour y revenir.
    $menuStore = ! $nav ? null : (request()->routeIs('team.*') ? $user?->store : ($currentStore ?? null));
    $items = [
        ['admin.orders.index', ['admin.orders.*', 'admin.customers.*'], 'inbox', 'Commandes'],
        ['admin.products.index', ['admin.products.*', 'admin.categories.*'], 'tag', 'Produits'],
        ['admin.share', ['admin.share*'], 'share', 'Partager'],
        ['admin.settings.store', ['admin.settings.*', 'admin.delivery.*', 'admin.payments.*', 'admin.subscription'], 'settings', 'Réglages'],
    ];
    // Ordinateur : un panneau plus large pour les listes, lisible pour les formulaires.
    $panel = $wide ? 'lg:max-w-wide' : 'lg:max-w-admin';
@endphp
<x-layouts.base :title="$title" class="min-h-dvh">
    {{-- Ordinateur : menu fixe à gauche. Téléphone : barre d'onglets en bas (plus bas). --}}
    @if ($user)
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-sidebar flex-col border-r border-border bg-bg lg:flex" aria-label="Menu principal">
            <a href="{{ $menuStore ? route('admin.orders.index') : route('home') }}" class="flex h-14 shrink-0 items-center gap-2 border-b border-border px-5">
                <img src="{{ asset('favicon.svg') }}" alt="" class="size-7" width="28" height="28">
                <span class="text-lg font-extrabold text-accent">Wanllan</span>
            </a>
            @if ($menuStore)
                <div class="border-b border-border px-5 py-4">
                    <p class="truncate font-semibold">{{ $menuStore->name }}</p>
                    <a href="{{ route('shop.show', $menuStore) }}" class="mt-0.5 inline-flex items-center gap-1 text-sm font-semibold text-accent" target="_blank" rel="noopener">
                        Voir ma boutique <x-icon name="external" :size="14" />
                    </a>
                </div>
            @endif
            <nav class="flex-1 space-y-1 overflow-y-auto p-3">
                @if ($menuStore)
                    @foreach ($items as [$route, $patterns, $icon, $label])
                        @php $active = request()->routeIs(...$patterns); @endphp
                        <a href="{{ route($route) }}" @if ($active) aria-current="page" @endif
                           class="flex min-h-tap items-center gap-3 rounded-md px-3 font-semibold {{ $active ? 'bg-accent-soft text-accent' : 'text-text-muted hover:bg-surface hover:text-text' }}">
                            <x-icon :name="$icon" :size="20" /> {{ $label }}
                        </a>
                    @endforeach
                @endif
                @if ($user->is_team)
                    @php $active = request()->routeIs('team.*'); @endphp
                    <a href="{{ route('team.dashboard') }}" @if ($active) aria-current="page" @endif
                       class="flex min-h-tap items-center gap-3 rounded-md px-3 font-semibold {{ $active ? 'bg-accent-soft text-accent' : 'text-text-muted hover:bg-surface hover:text-text' }} {{ $menuStore ? 'mt-4' : '' }}">
                        <x-icon name="dashboard" :size="20" /> Gestion Wanllan
                    </a>
                @endif
            </nav>
            <form method="POST" action="{{ route('logout') }}" class="border-t border-border p-3">
                @csrf
                <button class="flex min-h-tap w-full items-center gap-3 rounded-md px-3 text-sm font-semibold text-text-muted hover:bg-surface hover:text-text" type="submit">
                    <x-icon name="logout" :size="20" /> Se déconnecter
                </button>
            </form>
        </aside>
    @endif

    <div class="{{ $user ? 'lg:pl-sidebar' : '' }}">
        <div class="mx-auto flex min-h-dvh max-w-admin flex-col bg-bg lg:max-w-none lg:bg-transparent">
            <header class="sticky top-0 z-20 border-b border-border bg-bg lg:px-8">
                <div class="mx-auto flex h-14 items-center gap-3 px-4 {{ $panel }} lg:px-0">
                    @if ($back)
                        <a href="{{ $back }}" class="-ml-2 inline-flex min-h-tap min-w-tap items-center justify-center" aria-label="Retour">
                            <x-icon name="back" />
                        </a>
                    @endif
                    <h1 class="flex-1 truncate text-lg font-semibold lg:text-xl">{{ $title }}</h1>
                    @if ($user?->is_team && ! request()->routeIs('team.*'))
                        <a href="{{ route('team.dashboard') }}" class="btn-ghost text-sm font-semibold lg:hidden">Gestion</a>
                    @endif
                    @isset($currentStore)
                        <a href="{{ route('shop.show', $currentStore) }}" class="btn-ghost inline-flex items-center gap-1 text-sm font-semibold lg:hidden" target="_blank" rel="noopener">
                            Ma boutique <x-icon name="external" :size="16" />
                        </a>
                    @endisset
                </div>
            </header>

            <main class="flex-1 pb-24 lg:px-8 lg:py-6">
                <div class="mx-auto {{ $panel }}">
                    @isset($currentStore)
                        {{-- Rappel avant la fin d'une formule payante : jamais de prélèvement, donc on prévient. --}}
                        @php $left = $currentStore->planDaysLeft(); @endphp
                        @if ($left !== null && $left <= config('wanllan.reminder_days'))
                            <a href="{{ route('admin.subscription') }}" class="toast block bg-warning-soft text-on-warning-soft lg:mx-0 lg:mt-0 lg:mb-4">
                                Votre formule {{ \App\Support\Plans::get($currentStore->activePlan())['name'] }} se termine dans {{ $left }} jour{{ $left > 1 ? 's' : '' }}. Renouveler →
                            </a>
                        @endif
                    @endisset

                    <div class="lg:[&>.toast]:mx-0 lg:[&>.toast]:mt-0 lg:[&>.toast]:mb-4"><x-flash /></div>

                    <div class="lg:overflow-clip lg:rounded-lg lg:border lg:border-border lg:bg-bg lg:[&>.section:last-child]:border-b-0">{{ $slot }}</div>
                </div>
            </main>

            @isset($currentStore)
                <nav aria-label="Menu" class="fixed inset-x-0 bottom-0 z-30 mx-auto flex h-16 max-w-admin border-t border-border bg-bg pb-[env(safe-area-inset-bottom)] lg:hidden">
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
    </div>
</x-layouts.base>
