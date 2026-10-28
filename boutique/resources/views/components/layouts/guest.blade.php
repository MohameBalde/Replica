@props(['title' => null, 'wide' => false])
{{-- Téléphone : la page entière. Ordinateur et tablette : une carte centrée (plus large pour les pages à lire).
     $action : un lien ou un bouton en haut à droite (ex. « Se connecter » sur l'inscription), visible sans faire défiler. --}}
<x-layouts.base :title="$title" class="min-h-dvh">
    <main class="mx-auto max-w-content px-4 py-10 md:my-12 md:rounded-lg md:border md:border-border md:bg-bg md:p-8 {{ $wide ? 'md:max-w-admin lg:p-10' : '' }}">
        <div class="mb-6 flex items-center gap-2">
            <img src="{{ asset('favicon.svg') }}" alt="" class="size-9" width="36" height="36">
            <span class="text-xl font-extrabold text-accent">{{ config('app.name') }}</span>
            @isset($action)
                <div class="ml-auto">{{ $action }}</div>
            @else
                <span class="text-sm text-text-muted">· Votre boutique sur WhatsApp</span>
            @endisset
        </div>
        {{ $slot }}
    </main>
</x-layouts.base>
