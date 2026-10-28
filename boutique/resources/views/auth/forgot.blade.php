<x-layouts.guest title="Mot de passe oublié">
    <x-slot:action>
        <a href="{{ route('login') }}" class="inline-block py-2 text-sm font-semibold text-accent">Se connecter</a>
    </x-slot:action>

    <h1 class="text-display font-bold">Mot de passe oublié ?</h1>
    <p class="mt-3 text-text-muted">Écrivez-nous sur WhatsApp. Nous vous envoyons un lien pour choisir un nouveau mot de passe, sur le WhatsApp du numéro de votre compte.</p>

    <form method="POST" action="{{ route('password.forgot') }}" class="mt-8" novalidate>
        @csrf
        <div class="field">
            <label class="label" for="phone">Numéro de votre compte</label>
            <div class="input-group @error('phone') is-invalid @enderror">
                <span class="input-addon">+224</span>
                <input class="input" id="phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone') }}" placeholder="620 00 00 00" autocomplete="tel" required
                       @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror>
            </div>
            @error('phone')<p class="error" id="phone-error">{{ $message }}</p>@enderror
        </div>

        <button class="btn btn-whatsapp btn-lg mt-2" type="submit"><x-icon name="message" :size="20" /> Demander le lien sur WhatsApp</button>
        <p class="help text-center">Support : {{ config('wanllan.support_hours') }}. Le lien est valable 1 heure.</p>
    </form>
</x-layouts.guest>
