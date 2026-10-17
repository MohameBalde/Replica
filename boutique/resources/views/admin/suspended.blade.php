{{-- Espace commerçant d'une boutique suspendue : le motif, rien n'est perdu, et le support. --}}
@php
    $message = 'Bonjour Wanllan, ma boutique « '.$store->name.' » ('.route('shop.show', $store).') est suspendue. Je voudrais en parler.';
@endphp
<x-layouts.admin title="Boutique suspendue">
    <section class="section border-b-0">
        <p class="rounded-md bg-danger-soft p-4 text-on-danger-soft">
            <strong>{{ $store->name }}</strong> est suspendue depuis le {{ $store->suspended_at->timezone('Africa/Conakry')->format('d/m/Y') }}.<br>
            Motif : {{ $store->suspension_reason }}.
        </p>
        <p class="mt-4">Vos clients voient « Boutique momentanément indisponible » et ne peuvent pas commander.</p>
        <p class="mt-2"><strong>Rien n'est effacé</strong> : vos produits, vos commandes et vos clients sont gardés. Écrivez-nous pour en parler et rouvrir la boutique.</p>

        <a href="{{ \App\Services\WhatsAppMessage::url(config('wanllan.support_whatsapp'), $message) }}" class="btn btn-whatsapp mt-6" target="_blank" rel="noopener">
            <x-icon name="message" :size="20" /> Écrire au support Wanllan
        </a>
        <p class="help text-center">Support : {{ config('wanllan.support_hours') }}.</p>

        <form method="POST" action="{{ route('logout') }}" class="mt-6">
            @csrf
            <button class="btn btn-ghost w-full" type="submit">Se déconnecter</button>
        </form>
    </section>
</x-layouts.admin>
