<x-layouts.admin title="Catégories" :back="route('admin.products.index')">
    <p class="px-4 pt-4 text-sm text-text-muted">Ce sont les onglets en haut de votre vitrine, dans cet ordre.</p>

    @if ($categories->isEmpty())
        <p class="px-4 py-8 text-center text-text-muted">Pas encore de catégorie.</p>
    @else
        <ul class="mt-3 divide-y divide-border border-y border-border">
            @foreach ($categories as $category)
                <li class="flex items-center gap-2 px-4 py-3">
                    <div class="flex flex-col">
                        @foreach ([['up', '↑', 'Monter', $loop->first], ['down', '↓', 'Descendre', $loop->last]] as [$dir, $arrow, $label, $disabled])
                            <form method="POST" action="{{ route('admin.categories.move', $category->id) }}">
                                @csrf <input type="hidden" name="direction" value="{{ $dir }}">
                                <button class="grid h-7 w-9 place-items-center rounded-sm text-lg font-bold text-text-muted disabled:opacity-30" aria-label="{{ $label }} {{ $category->name }}" @disabled($disabled)>{{ $arrow }}</button>
                            </form>
                        @endforeach
                    </div>
                    <form method="POST" action="{{ route('admin.categories.update', $category->id) }}" class="flex min-w-0 flex-1 gap-2">
                        @csrf @method('PUT')
                        <label class="sr-only" for="c{{ $category->id }}">Nom</label>
                        <input class="input min-w-0" id="c{{ $category->id }}" name="name" value="{{ $category->name }}" maxlength="80">
                        <button class="btn btn-secondary w-auto shrink-0 px-3" type="submit">OK</button>
                    </form>
                    <form method="POST" action="{{ route('admin.categories.destroy', $category->id) }}"
                          onsubmit="return confirm('Supprimer « {{ addslashes($category->name) }} » ? Ses {{ $category->products_count }} produit(s) restent en vente.')">
                        @csrf @method('DELETE')
                        <button class="grid size-tap place-items-center text-danger" aria-label="Supprimer {{ $category->name }}">✕</button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif

    <form method="POST" action="{{ route('admin.categories.store') }}" class="flex gap-2 p-4">
        @csrf
        <label class="sr-only" for="new-category">Nouvelle catégorie</label>
        <input class="input @error('name') is-invalid @enderror" id="new-category" name="name" placeholder="Nouvelle catégorie, ex. Parfums" maxlength="80">
        <button class="btn btn-primary w-auto shrink-0" type="submit">Ajouter</button>
    </form>
</x-layouts.admin>
