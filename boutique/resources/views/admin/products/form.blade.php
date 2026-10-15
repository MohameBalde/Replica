@php
    $editing = $product->exists;
    $variant = $editing ? $product->variants->first() : null;
@endphp
<x-layouts.admin :title="$editing ? 'Modifier le produit' : 'Nouveau produit'" :back="route('admin.products.index')">
    <form method="POST" action="{{ $editing ? route('admin.products.update', $product->id) : route('admin.products.store') }}" class="p-4" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="field">
            <label class="label" for="name">Nom du produit</label>
            <input class="input @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $product->name) }}" required maxlength="150" placeholder="Ex. Baskets blanches classiques">
            @error('name')<p class="error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label class="label" for="price">Prix</label>
            <div class="input-group @error('price') is-invalid @enderror">
                <input class="input" id="price" name="price" inputmode="numeric" value="{{ old('price', $variant ? gnf($variant->price, false) : '') }}" required placeholder="150 000">
                <span class="input-addon">GNF</span>
            </div>
            @error('price')<p class="error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label class="label" for="stock">Quantité en stock <span class="font-normal text-text-muted">(facultatif)</span></label>
            <input class="input @error('stock') is-invalid @enderror" id="stock" name="stock" inputmode="numeric" value="{{ old('stock', $variant?->stock) }}" aria-describedby="stock-help">
            <p class="help" id="stock-help">Laissez vide si vous ne comptez pas votre stock. Sinon, le produit passe « Épuisé » tout seul.</p>
            @error('stock')<p class="error">{{ $message }}</p>@enderror
        </div>

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
        @endif

        <p class="help mb-4">Photos et variantes (tailles, couleurs) : prochaine étape.</p>

        <button class="btn btn-primary btn-lg" type="submit">{{ $editing ? 'Enregistrer' : 'Ajouter le produit' }}</button>
    </form>
</x-layouts.admin>
