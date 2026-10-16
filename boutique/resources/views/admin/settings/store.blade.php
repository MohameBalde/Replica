<x-layouts.admin title="Réglages">
    @include('admin.settings._tabs')

    <form method="POST" action="{{ route('admin.settings.store.update') }}" class="p-4" novalidate>
        @csrf @method('PUT')

        <label class="card mb-4 flex items-center gap-3 p-4">
            <span class="flex-1">
                <span class="block font-bold">Boutique ouverte</span>
                <span class="text-sm text-text-muted">Fermée : les clients voient vos produits mais ne peuvent pas commander.</span>
            </span>
            <span class="switch"><input type="checkbox" role="switch" name="is_open" value="1" @checked(old('is_open', $store->is_open))><span></span></span>
        </label>

        <div class="field">
            <label class="label" for="name">Nom de la boutique</label>
            <input class="input @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name', $store->name) }}" required maxlength="120">
            @error('name')<p class="error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label class="label" for="description">Ce que vous vendez <span class="font-normal text-text-muted">(facultatif)</span></label>
            <input class="input" id="description" name="description" value="{{ old('description', $store->description) }}" maxlength="500" placeholder="Ex. Chaussures, survêtements et parfums">
            <p class="help">Affiché sous le nom de la boutique.</p>
        </div>

        <div class="field">
            <label class="label" for="whatsapp_number">Numéro WhatsApp qui reçoit les commandes</label>
            <div class="input-group @error('whatsapp_number') is-invalid @enderror">
                <span class="input-addon">+224</span>
                <input class="input" id="whatsapp_number" name="whatsapp_number" type="tel" inputmode="tel" value="{{ old('whatsapp_number', substr(phone_display($store->whatsapp_number), 5)) }}" required>
            </div>
            @error('whatsapp_number')<p class="error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label class="label" for="city">Ville</label>
            <select class="input" id="city" name="city">
                @foreach ($cities as $city)<option value="{{ $city }}" @selected(old('city', $store->city) === $city)>{{ $city }}</option>@endforeach
            </select>
        </div>

        <p class="help mb-4">Lien de la boutique : <span class="font-mono">{{ route('shop.show', $store) }}</span> (il ne change pas, pour ne pas casser vos pubs).</p>

        <button class="btn btn-primary btn-lg" type="submit">Enregistrer</button>
    </form>

    <form method="POST" action="{{ route('logout') }}" class="px-4 pb-6">
        @csrf
        <button class="btn btn-ghost w-full" type="submit">Se déconnecter</button>
    </form>

    {{-- Fermé par défaut : on ne supprime pas son compte par erreur. --}}
    <details class="mx-4 mb-4 rounded-md border border-danger p-4" @if ($errors->has('password')) open @endif>
        <summary class="cursor-pointer font-bold text-danger">Supprimer mon compte</summary>
        <form method="POST" action="{{ route('admin.account.destroy') }}" class="mt-3" novalidate>
            @csrf @method('DELETE')
            <p class="text-sm">Tout sera effacé : la boutique, les produits et leurs photos, les commandes, les clients et les captures de paiement. <strong>Impossible de revenir en arrière.</strong></p>
            <div class="field mt-3">
                <label class="label" for="delete-password">Votre mot de passe</label>
                <input class="input @error('password') is-invalid @enderror" id="delete-password" name="password" type="password" autocomplete="current-password" required
                       @error('password') aria-invalid="true" aria-describedby="delete-password-error" @enderror>
                @error('password')<p class="error" id="delete-password-error">{{ $message }}</p>@enderror
            </div>
            <button class="btn btn-danger" type="submit">Supprimer définitivement</button>
        </form>
    </details>

    <p class="px-4 pb-8 text-center text-xs text-text-muted">
        <a href="{{ route('legal.terms') }}" class="font-semibold text-accent">Conditions</a> ·
        <a href="{{ route('legal.privacy') }}" class="font-semibold text-accent">Confidentialité</a>
    </p>
</x-layouts.admin>
