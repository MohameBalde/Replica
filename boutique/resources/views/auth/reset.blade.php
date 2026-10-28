<x-layouts.guest title="Nouveau mot de passe">
    @if (! $user)
        <h1 class="text-display font-bold">Ce lien ne marche plus</h1>
        <p class="mt-3 text-text-muted">Il a déjà servi, ou il a plus d'une heure. Demandez-en un nouveau : c'est rapide.</p>
        <a href="{{ route('password.forgot') }}" class="btn btn-primary btn-lg mt-8">Demander un nouveau lien</a>
        <p class="mt-6 text-center text-sm text-text-muted">Vous vous souvenez de votre mot de passe ? <a class="font-semibold text-accent" href="{{ route('login') }}">Se connecter</a></p>
    @else
        <h1 class="text-display font-bold">Nouveau mot de passe</h1>
        <p class="mt-3 text-text-muted">Bonjour {{ $user->name }}, choisissez le nouveau mot de passe du compte {{ phone_display($user->phone) }}.</p>

        <form method="POST" action="{{ route('password.update', $token) }}" class="mt-8" novalidate>
            @csrf
            {{-- Pour que le téléphone propose d'enregistrer le mot de passe avec le bon numéro. --}}
            <input type="text" name="username" value="{{ $user->phone }}" autocomplete="username" class="hidden" readonly tabindex="-1" aria-hidden="true">

            <div class="field">
                <label class="label" for="password">Nouveau mot de passe</label>
                <input class="input @error('password') is-invalid @enderror" id="password" name="password" type="password" autocomplete="new-password" required
                       aria-describedby="password-help @error('password') password-error @enderror" @error('password') aria-invalid="true" @enderror>
                <p class="help" id="password-help">Au moins 8 caractères.</p>
                @error('password')<p class="error" id="password-error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label class="label" for="password_confirmation">Confirmez le mot de passe</label>
                <input class="input" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            </div>

            <button class="btn btn-primary btn-lg mt-2" type="submit">Enregistrer et me connecter</button>
        </form>
    @endif
</x-layouts.guest>
