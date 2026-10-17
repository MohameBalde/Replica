@props(['store', 'title' => null, 'description' => null, 'image' => null, 'wide' => false])
{{-- Téléphone : une colonne. Ordinateur : vitrine et fiche produit en largeur, panier et commande en carte centrée. --}}
<x-layouts.base :title="$title ?? $store->name" :description="$description ?? ($store->description ?: 'Commandez en ligne, la commande arrive sur WhatsApp.')" :image="$image" class="min-h-dvh">
    <div class="mx-auto flex min-h-dvh max-w-content flex-col bg-bg md:max-w-admin {{ $wide ? 'lg:max-w-wide' : 'lg:my-10 lg:min-h-0 lg:max-w-form lg:rounded-lg lg:border lg:border-border' }}">
        {{ $slot }}
        {{-- Chaque vitrine fait connaître Wanllan auprès d'autres commerçants. --}}
        @if ($store->showsBadge())
            <p class="py-6 text-center text-xs text-text-muted">
                Boutique créée avec <a href="{{ route('home') }}?src=vitrine" class="font-semibold text-accent">Wanllan</a>
            </p>
        @endif
    </div>
</x-layouts.base>
