<x-layouts.guest title="Créer ma boutique">
    <h1 class="text-display font-bold">Votre boutique</h1>
    <p class="mt-3 text-text-muted">Ces informations apparaîtront chez vos clients. Vous pourrez les changer plus tard.</p>

    <form method="POST" action="{{ route('admin.store.store') }}" class="mt-8" novalidate>
        @csrf
        <div class="field">
            <label class="label" for="name">Nom de la boutique</label>
            <input class="input @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" placeholder="Ex. NimStore" required maxlength="120"
                   oninput="if(!document.getElementById('slug').dataset.touched){document.getElementById('slug').value=this.value.normalize('NFD').replace(/[̀-ͯ]/g,'').toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'')}">
            @error('name')<p class="error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label class="label" for="slug">Lien de votre boutique</label>
            <div class="input-group @error('slug') is-invalid @enderror">
                <span class="input-addon text-sm">{{ preg_replace('#^https?://#', '', url('/')) }}/</span>
                <input class="input pl-0" id="slug" name="slug" value="{{ old('slug') }}" placeholder="nimstore" required maxlength="60"
                       autocapitalize="none" spellcheck="false" oninput="this.dataset.touched=1" aria-describedby="slug-help">
            </div>
            <p class="help" id="slug-help">C'est ce lien que vous mettrez dans vos pubs et sur WhatsApp.</p>
            @error('slug')<p class="error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label class="label" for="whatsapp_number">Numéro WhatsApp qui reçoit les commandes</label>
            <div class="input-group @error('whatsapp_number') is-invalid @enderror">
                <span class="input-addon">+224</span>
                <input class="input" id="whatsapp_number" name="whatsapp_number" type="tel" inputmode="tel"
                       value="{{ old('whatsapp_number', substr(preg_replace('/\D/', '', $suggestedPhone), 3)) }}" required>
            </div>
            @error('whatsapp_number')<p class="error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label class="label" for="city">Ville</label>
            <select class="input" id="city" name="city" required>
                @foreach ($cities as $city)
                    <option value="{{ $city }}" @selected(old('city', 'Conakry') === $city)>{{ $city }}</option>
                @endforeach
            </select>
            @error('city')<p class="error">{{ $message }}</p>@enderror
        </div>

        <button class="btn btn-primary btn-lg mt-2" type="submit">Créer ma boutique</button>
        <p class="help text-center">Le retrait en boutique et le paiement à la livraison sont activés au départ. Vous pourrez les modifier.</p>
    </form>
</x-layouts.guest>
