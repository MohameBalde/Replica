@props(['title' => null])
<x-layouts.base :title="$title" class="min-h-dvh">
    <main class="mx-auto max-w-content px-4 py-10">
        <p class="mb-6 flex items-center gap-2">
            <img src="{{ asset('favicon.svg') }}" alt="" class="size-9" width="36" height="36">
            <span class="text-xl font-extrabold text-accent">{{ config('app.name') }}</span>
            <span class="text-sm text-text-muted">· Votre boutique sur WhatsApp</span>
        </p>
        {{ $slot }}
    </main>
</x-layouts.base>
