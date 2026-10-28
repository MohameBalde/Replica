<x-layouts.guest title="Connexion">
    <h1 class="text-display font-bold">Bon retour !</h1>
    <p class="mt-3 text-text-muted">Connectez-vous pour voir vos commandes.</p>

    <form method="POST" action="{{ route('login') }}" class="mt-8" novalidate>
        @csrf
        <div class="field">
            <label class="label" for="phone">Numéro de téléphone</label>
            <div class="input-group @error('phone') is-invalid @enderror">
                <span class="input-addon">+224</span>
                <input class="input" id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone') }}" placeholder="620 00 00 00" autocomplete="tel" required
                       @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
            </div>
            @error('phone')<p class="error" id="phone-error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label class="label" for="password">Mot de passe</label>
            <input class="input" id="password" name="password" type="password" autocomplete="current-password" required>
        </div>

        <button class="btn btn-primary btn-lg mt-2" type="submit">Se connecter</button>
    </form>

    <p class="mt-6 text-center text-sm text-text-muted">Pas encore de boutique ? <a class="font-semibold text-accent" href="{{ route('register') }}">Créer mon compte</a></p>
    <p class="mt-2 text-center text-sm"><a class="font-semibold text-accent" href="{{ route('password.forgot') }}">Mot de passe oublié ?</a></p>
</x-layouts.guest>
