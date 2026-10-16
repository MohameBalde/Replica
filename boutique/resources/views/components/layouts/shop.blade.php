@props(['store', 'title' => null, 'description' => null])
<x-layouts.base :title="$title ?? $store->name" :description="$description ?? $store->description" class="min-h-dvh">
    <div class="mx-auto flex min-h-dvh max-w-content flex-col bg-bg">
        {{ $slot }}
        {{-- Chaque vitrine fait connaître Wanllan auprès d'autres commerçants. --}}
        @if ($store->showsBadge())
            <p class="py-6 text-center text-xs text-text-muted">
                Boutique créée avec <a href="{{ route('home') }}?src=vitrine" class="font-semibold text-accent">Wanllan</a>
            </p>
        @endif
    </div>
</x-layouts.base>
