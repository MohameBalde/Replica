@props(['store', 'title' => null, 'description' => null, 'image' => null, 'wide' => false])
{{-- Téléphone : une colonne. Ordinateur : vitrine et fiche produit en largeur, panier et commande en carte centrée. --}}
<x-layouts.base :title="$title ?? $store->name" :description="$description ?? ($store->description ?: 'Commandez en ligne, la commande arrive sur WhatsApp.')" :image="$image" class="min-h-dvh">
    <div class="mx-auto flex min-h-dvh max-w-content flex-col bg-bg md:max-w-admin {{ $wide ? 'lg:max-w-wide' : 'lg:my-10 lg:min-h-0 lg:max-w-form lg:rounded-lg lg:border lg:border-border' }}">
        {{-- Le commerçant qui regarde sa propre vitrine : dans l'app installée sur ordinateur, la fenêtre n'a pas de bouton « retour ».
             Ouverte depuis son espace (« Voir ma boutique », nouvelle fenêtre) : on la referme ; sinon on va à son Accueil. Les clients ne voient pas cette barre. --}}
        @if (auth()->check() && (int) auth()->id() === (int) $store->user_id)
            <div class="flex items-center gap-2 border-b border-border bg-surface px-4 text-sm lg:px-8">
                <a href="{{ route('admin.home') }}" class="inline-flex min-h-tap items-center gap-1 font-semibold text-accent"
                   onclick="if (history.length === 1 && document.referrer.startsWith(location.origin + '/admin')) window.close()">
                    <x-icon name="back" :size="18" /> Retour à mon espace
                </a>
                <span class="ml-auto text-text-muted">Vue client</span>
            </div>
        @endif
        {{ $slot }}
        {{-- Chaque vitrine fait connaître Wanllan auprès d'autres commerçants. --}}
        @if ($store->showsBadge())
            <p class="py-6 text-center text-xs text-text-muted">
                Boutique créée avec <a href="{{ route('home') }}?src=vitrine" class="font-semibold text-accent">Wanllan</a>
            </p>
        @endif
    </div>
</x-layouts.base>
