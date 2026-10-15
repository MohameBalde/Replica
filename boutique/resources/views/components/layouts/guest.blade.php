@props(['title' => null])
<x-layouts.base :title="$title" class="min-h-dvh">
    <main class="mx-auto max-w-content px-4 py-10">
        <p class="mb-6 text-sm font-bold uppercase tracking-wider text-accent">{{ config('app.name') }}</p>
        {{ $slot }}
    </main>
</x-layouts.base>
