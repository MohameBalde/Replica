<x-layouts.guest title="Créer mon compte">
    <x-slot:action>
        <a href="{{ route('login') }}" class="inline-block py-2 text-sm font-semibold text-accent">Se connecter</a>
    </x-slot:action>

    <h1 class="text-display font-bold">Vendez sur WhatsApp, sans répéter vos prix 50 fois.</h1>
    <p class="mt-3 text-text-muted">Créez votre boutique en 2 minutes. Vos clients choisissent, commandent, et la commande arrive sur votre WhatsApp.</p>

    <form method="POST" action="{{ route('register') }}" class="mt-8" novalidate>
        @csrf
        <div class="field">
            <label class="label" for="name">Votre nom</label>
            <input class="input @error('name') is-invalid @enderror" id="name" name="name" value="{{ old('name') }}" autocomplete="name" required
                   @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
            @error('name')<p class="error" id="name-error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label class="label" for="phone">Numéro de téléphone</label>
            <div class="input-group @error('phone') is-invalid @enderror">
                <span class="input-addon">+224</span>
                <input class="input" id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone') ? substr(old('phone'), -9) : '' }}" placeholder="620 00 00 00" autocomplete="tel" required
                       aria-describedby="phone-help @error('phone') phone-error @enderror" @error('phone') aria-invalid="true" @enderror>
            </div>
            <p class="help" id="phone-help">Il vous servira à vous connecter.</p>
            @error('phone')
                <p class="error" id="phone-error">{{ $message }}
                    @if ($message === \App\Http\Controllers\Auth\RegisterController::PHONE_TAKEN)
                        <a class="font-semibold text-accent underline" href="{{ route('login') }}">Se connecter</a>
                    @endif
                </p>
            @enderror
        </div>

        <div class="field">
            <label class="label" for="password">Mot de passe</label>
            <input class="input @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="new-password" required
                   aria-describedby="password-help @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror>
            <p class="help" id="password-help">Au moins 8 caractères.</p>
            @error('password')<p class="error" id="password-error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label class="label" for="password_confirmation">Confirmez le mot de passe</label>
            <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
        </div>

        <button class="btn btn-primary btn-lg mt-2" type="submit">Créer mon compte</button>
        <p class="help text-center">En créant un compte, vous acceptez les <a class="font-semibold text-accent" href="{{ route('legal.terms') }}">conditions d'utilisation</a> et la <a class="font-semibold text-accent" href="{{ route('legal.privacy') }}">politique de confidentialité</a>.</p>
    </form>

    <p class="mt-6 text-center text-sm text-text-muted">Déjà un compte ? <a class="font-semibold text-accent" href="{{ route('login') }}">Se connecter</a></p>
</x-layouts.guest>
