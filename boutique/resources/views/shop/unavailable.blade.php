{{-- Boutique suspendue par l'équipe Wanllan : aucun produit, aucune commande possible. --}}
<x-layouts.base :title="$store->name" description="Boutique momentanément indisponible." class="min-h-dvh">
    <main class="mx-auto flex min-h-dvh max-w-content flex-col items-center justify-center bg-bg px-6 text-center">
        <p class="text-2xl font-bold">{{ $store->name }}</p>
        <p class="mt-3 text-lg">Boutique momentanément indisponible.</p>
        <p class="mt-2 text-text-muted">Les commandes sont fermées pour le moment.</p>
        <a href="{{ route('home') }}?src=indisponible" class="mt-10 text-xs text-text-muted">Wanllan · Votre boutique sur WhatsApp</a>
    </main>
</x-layouts.base>
