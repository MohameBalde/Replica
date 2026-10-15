@props(['store', 'title' => null, 'description' => null])
<x-layouts.base :title="$title ?? $store->name" :description="$description ?? $store->description" class="min-h-dvh">
    <div class="mx-auto flex min-h-dvh max-w-content flex-col bg-bg">
        {{ $slot }}
    </div>
</x-layouts.base>
