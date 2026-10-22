@php
    $editing = $product->exists;
    $variants = $editing ? $product->variants : collect();
    $hasOptions = $variants->count() > 1 || ($variants->first() && $variants->first()->name !== 'Standard');
    $base = $hasOptions ? null : $variants->first();
    $rows = old('variants', $hasOptions
        ? $variants->map(fn ($v) => ['id' => $v->id, 'name' => $v->name, 'price' => gnf($v->price, false), 'stock' => $v->stock])->all()
        : []);
    $rows = array_values($rows);
    $rows[] = ['id' => '', 'name' => '', 'price' => '', 'stock' => '']; // une ligne vide prête à remplir
    $photoCount = $editing ? $product->images->count() : 0;
    // Suppléments payants : lignes enregistrées (ou renvoyées après une erreur) + une ligne vide.
    $addonRows = array_values(old('addons', $product->addonList()
        ->map(fn ($a) => ['key' => $a['key'], 'name' => $a['name'], 'price' => $a['price'] ? gnf($a['price'], false) : '', 'text' => $a['text']])
        ->values()->all()));
    $hasAddons = $addonRows !== [];
    if (count($addonRows) < \App\Models\Product::MAX_ADDONS) {
        $addonRows[] = ['key' => '', 'name' => '', 'price' => '', 'text' => ''];
    }
@endphp
<x-layouts.admin :title="$editing ? 'Modifier le produit' : 'Nouveau produit'" :back="route('admin.products.index')">
    <form method="POST" action="{{ $editing ? route('admin.products.update', $product->id) : route('admin.products.store') }}"
          enctype="multipart/form-data" class="p-4" novalidate onsubmit="this.querySelector('[type=submit]').disabled = true">
        @csrf
        @if ($editing) @method('PUT') @endif

        {{-- Photos --}}
        <fieldset class="field">
            <legend class="label">Photos <span class="font-normal text-text-muted">({{ \App\Http\Controllers\Admin\ProductController::MAX_PHOTOS }} maximum)</span></legend>
            @if ($photoCount)
                <ul class="mb-3 grid grid-cols-4 gap-2">
                    @foreach ($product->images as $image)
                        <li class="relative">
                            <img src="{{ asset('storage/'.$image->thumb_path) }}" alt="Photo {{ $loop->iteration }} de {{ $product->name }}" class="aspect-square w-full rounded-md object-cover">
                            <label class="mt-1 flex items-center gap-1 text-xs text-danger">
                                <input type="checkbox" name="remove_images[]" value="{{ $image->id }}" class="accent-danger"> Retirer
                            </label>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($photoCount < \App\Http\Controllers\Admin\ProductController::MAX_PHOTOS)
                <label class="flex min-h-24 cursor-pointer flex-col items-center justify-center gap-1 rounded-md border-2 border-dashed border-border-input bg-surface p-4 text-center has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-focus">
                    <x-icon name="plus" />
                    <span class="font-semibold">Ajouter des photos</span>
                    <span class="text-sm text-text-muted" id="photos-help">JPG ou PNG, 8 Mo max. Compressées automatiquement.</span>
                    <input type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" aria-describedby="photos-help"
                           onchange="this.closest('label').querySelector('#photos-help').textContent = this.files.length + ' photo(s) choisie(s)'">
                </label>
            @endif
            @error('photos')<p class="error">{{ $message }}</p>@enderror
            @error('photos.*')<p class="error">{{ $message }}</p>@enderror
        </fieldset>

        <div class="field">
            <label class="label" for="name">Nom du produit</label>
            <input class="input @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $product->name) }}" required maxlength="150" placeholder="Ex. Baskets blanches classiques">
            @error('name')<p class="error">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div class="field">
                <label class="label" for="price">Prix</label>
                <div class="input-group @error('price') is-invalid @enderror">
                    <input class="input" id="price" name="price" inputmode="numeric" required placeholder="150 000"
                           value="{{ old('price', $editing ? gnf($product->fromPrice(), false) : '') }}">
                    <span class="input-addon">GNF</span>
                </div>
                @error('price')<p class="error">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label class="label" for="stock">Stock <span class="font-normal text-text-muted">(facultatif)</span></label>
                <input class="input @error('stock') is-invalid @enderror" id="stock" name="stock" inputmode="numeric" value="{{ old('stock', $base?->stock) }}" @if ($hasOptions) disabled placeholder="Par choix ↓" @endif>
            </div>
        </div>
        <p class="help -mt-2 mb-4">Stock vide = vous ne comptez pas. Sinon, le produit passe « Épuisé » tout seul.</p>

        {{-- Tailles / couleurs : <details> marche sans JavaScript. --}}
        <details class="card mb-4" @if ($hasOptions || old('variants')) open @endif
                 ontoggle="document.getElementById('stock').disabled = this.open; document.getElementById('stock').placeholder = this.open ? 'Par choix ↓' : ''">
            <summary class="flex min-h-tap cursor-pointer items-center gap-2 p-4 font-semibold">
                <x-icon name="plus" :size="18" /> Plusieurs tailles ou couleurs
            </summary>
            <div class="border-t border-border p-4">
                <p class="help mt-0 mb-3">Une ligne par choix, ex. « 42 / Noir ». Prix vide = prix principal.</p>
                <div id="variant-rows" class="space-y-3">
                    @foreach ($rows as $i => $row)
                        <div class="grid grid-cols-[1fr_7rem_4.5rem] gap-2" data-row>
                            <input type="hidden" name="variants[{{ $i }}][id]" value="{{ $row['id'] }}">
                            <label class="sr-only" for="v{{ $i }}-name">Choix</label>
                            <input class="input" id="v{{ $i }}-name" name="variants[{{ $i }}][name]" value="{{ $row['name'] }}" placeholder="Ex. 42 / Noir" maxlength="80">
                            <label class="sr-only" for="v{{ $i }}-price">Prix</label>
                            <input class="input" id="v{{ $i }}-price" name="variants[{{ $i }}][price]" value="{{ $row['price'] }}" inputmode="numeric" placeholder="Prix">
                            <label class="sr-only" for="v{{ $i }}-stock">Stock</label>
                            <input class="input" id="v{{ $i }}-stock" name="variants[{{ $i }}][stock]" value="{{ $row['stock'] }}" inputmode="numeric" placeholder="Stock">
                        </div>
                    @endforeach
                </div>
                @error('variant_rows')<p class="error">{{ $message }}</p>@enderror
                @error('variant_rows.*')<p class="error">{{ $message }}</p>@enderror
                <button type="button" class="btn btn-secondary mt-3" onclick="
                    const rows = document.getElementById('variant-rows');
                    const last = rows.lastElementChild;
                    const copy = last.cloneNode(true);
                    const n = rows.children.length;
                    copy.querySelectorAll('input, label').forEach(el => {
                        ['name', 'id', 'for'].forEach(a => { if (el.hasAttribute(a) && !(a === 'id' && el.type === 'hidden')) el.setAttribute(a, el.getAttribute(a).replace(/\d+/, n)); });
                        if (el.tagName === 'INPUT') el.value = '';
                    });
                    rows.appendChild(copy);
                    copy.querySelector('input:not([type=hidden])').focus();">
                    <x-icon name="plus" :size="18" /> Ajouter une ligne
                </button>
                <p class="help">Pour retirer un choix, videz son nom.</p>
            </div>
        </details>

        {{-- Suppléments payants (ex. flocage) : le client les coche sur la fiche produit, avec un texte si besoin. --}}
        <details class="card mb-4" @if ($hasAddons) open @endif>
            <summary class="flex min-h-tap cursor-pointer items-center gap-2 p-4 font-semibold">
                <x-icon name="plus" :size="18" /> Suppléments payants <span class="font-normal text-text-muted">(ex. flocage)</span>
            </summary>
            <div class="border-t border-border p-4">
                <p class="help mt-0 mb-3">Le client coche les suppléments qu'il veut ; leur prix s'ajoute à l'article. « Texte demandé » : ce que le client doit écrire, ex. « Nom et numéro au dos ».</p>
                <div id="addon-rows" class="divide-y divide-border">
                    @foreach ($addonRows as $i => $row)
                        <div class="grid grid-cols-[1fr_7rem] gap-2 py-3 first:pt-0" data-addon-row>
                            <input type="hidden" name="addons[{{ $i }}][key]" value="{{ $row['key'] ?? '' }}">
                            <label class="sr-only" for="a{{ $i }}-name">Supplément</label>
                            <input class="input" id="a{{ $i }}-name" name="addons[{{ $i }}][name]" value="{{ $row['name'] ?? '' }}" placeholder="Ex. Flocage" maxlength="60">
                            <label class="sr-only" for="a{{ $i }}-price">Prix du supplément</label>
                            <div class="input-group">
                                <input class="input" id="a{{ $i }}-price" name="addons[{{ $i }}][price]" value="{{ $row['price'] ?? '' }}" inputmode="numeric" placeholder="Prix">
                            </div>
                            <label class="sr-only" for="a{{ $i }}-text">Texte demandé au client</label>
                            <input class="input col-span-2" id="a{{ $i }}-text" name="addons[{{ $i }}][text]" value="{{ $row['text'] ?? '' }}" placeholder="Texte à demander (facultatif)" maxlength="60">
                        </div>
                    @endforeach
                </div>
                @error('addon_rows')<p class="error">{{ $message }}</p>@enderror
                @error('addon_rows.*')<p class="error">{{ $message }}</p>@enderror
                <button type="button" class="btn btn-secondary mt-3" onclick="
                    const rows = document.getElementById('addon-rows');
                    if (rows.children.length >= {{ \App\Models\Product::MAX_ADDONS }}) return;
                    const copy = rows.lastElementChild.cloneNode(true);
                    const n = rows.children.length;
                    copy.querySelectorAll('input, label').forEach(el => {
                        ['name', 'id', 'for'].forEach(a => { if (el.hasAttribute(a) && !(a === 'id' && el.type === 'hidden')) el.setAttribute(a, el.getAttribute(a).replace(/\d+/, n)); });
                        if (el.tagName === 'INPUT') el.value = '';
                    });
                    rows.appendChild(copy);
                    copy.querySelector('input:not([type=hidden])').focus();">
                    <x-icon name="plus" :size="18" /> Ajouter un supplément
                </button>
                <p class="help">Pour retirer un supplément, videz son nom. Prix vide = gratuit.</p>
            </div>
        </details>

        <div class="field">
            <label class="label" for="category">Catégorie <span class="font-normal text-text-muted">(facultatif)</span></label>
            <input class="input" id="category" name="category" list="categories" value="{{ old('category', $product->category?->name) }}" placeholder="Ex. Chaussures" maxlength="80">
            <datalist id="categories">
                @foreach ($categories as $category)<option value="{{ $category->name }}">@endforeach
            </datalist>
        </div>

        <div class="field">
            <label class="label" for="description">Description <span class="font-normal text-text-muted">(facultatif)</span></label>
            <textarea class="input" id="description" name="description" rows="4" maxlength="2000">{{ old('description', $product->description) }}</textarea>
        </div>

        @if ($editing)
            <label class="card mb-4 flex items-center gap-3 p-4">
                <span class="flex-1">
                    <span class="block font-semibold">Visible dans la boutique</span>
                    <span class="text-sm text-text-muted">Coupez pour cacher le produit sans le supprimer.</span>
                </span>
                <span class="switch"><input type="checkbox" role="switch" name="is_visible" value="1" @checked(old('is_visible', $product->is_visible))><span></span></span>
            </label>
            <p class="help mb-4">Lien direct pour vos pubs : <span class="font-mono break-all">{{ route('shop.product', [$product->store, $product]) }}</span></p>
        @endif

        <button class="btn btn-primary btn-lg" type="submit">{{ $editing ? 'Enregistrer' : 'Ajouter le produit' }}</button>
    </form>
</x-layouts.admin>
