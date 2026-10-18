@php
    $questions = array_values(old('questions', $store->checkoutQuestions()));
    $questions = array_pad($questions, \App\Http\Controllers\Admin\CheckoutSettingsController::MAX_QUESTIONS, ['label' => '', 'required' => false]);
    $hours = $store->openingHours();
    $useHours = (bool) old('use_hours', $hours->isSet());
    $examples = ['Point de repère (ex. près de la mosquée de Kipé)', 'Taille ou pointure', 'Heure de livraison'];
@endphp
<x-layouts.admin title="Réglages">
    @include('admin.settings._tabs')

    <form method="POST" action="{{ route('admin.checkout.update') }}" class="p-4" novalidate>
        @csrf @method('PUT')

        <h2 class="section-title">Questions au client</h2>
        <p class="help mt-0 mb-3">Posées au moment de commander. Les réponses arrivent avec la commande, dans WhatsApp et sur la facture.</p>
        <div class="space-y-3">
            @foreach ($questions as $i => $q)
                <div class="grid grid-cols-[1fr_auto] items-center gap-3">
                    <div>
                        <label class="sr-only" for="q{{ $i }}">Question {{ $i + 1 }}</label>
                        <input class="input @error("questions.$i.label") is-invalid @enderror" id="q{{ $i }}" name="questions[{{ $i }}][label]" value="{{ $q['label'] }}" maxlength="80" placeholder="{{ $examples[$i] }}">
                    </div>
                    <label class="flex min-h-tap items-center gap-2 text-sm">
                        <input type="checkbox" name="questions[{{ $i }}][required]" value="1" class="size-5 accent-accent" @checked(! empty($q['required']))> Obligatoire
                    </label>
                </div>
                @error("questions.$i.label")<p class="error -mt-2">{{ $message }}</p>@enderror
            @endforeach
        </div>
        <p class="help">Une case vide = pas de question.</p>

        <h2 class="section-title mt-8">Horaires d'ouverture</h2>
        <label class="card mb-4 flex items-center gap-3 p-4">
            <span class="flex-1">
                <span class="block font-semibold">Afficher mes horaires</span>
                <span class="text-sm text-text-muted">La vitrine dit « Ouvert jusqu'à 20 h » ou « Fermé · ouvre demain à 8 h ».</span>
            </span>
            <span class="switch"><input type="checkbox" role="switch" name="use_hours" value="1" @checked($useHours)
                  onchange="document.getElementById('hours').hidden = !this.checked"><span></span></span>
        </label>

        <div id="hours" @unless ($useHours) hidden @endunless>
            @error('hours')<p class="error mb-2">{{ $message }}</p>@enderror
            <div class="divide-y divide-border rounded-md border border-border">
                @foreach (\App\Support\OpeningHours::DAYS as $day => $name)
                    @php
                        $slot = $hours->isSet() ? $hours->slot($day) : ($day <= 6 ? ['open' => '08:00', 'close' => '20:00'] : null);
                        $open = old("hours.$day.open_day", $slot !== null);
                    @endphp
                    {{-- Téléphone : le jour, puis les deux heures sur une ligne. Plus large : une seule ligne. --}}
                    <div class="grid grid-cols-2 items-center gap-x-2 gap-y-1 px-3 py-2 md:grid-cols-[8rem_1fr_1fr]">
                        <label class="col-span-2 flex min-h-tap items-center gap-2 font-semibold md:col-span-1">
                            <input type="checkbox" name="hours[{{ $day }}][open_day]" value="1" class="size-5 accent-accent" @checked($open)> {{ $name }}
                        </label>
                        <label class="sr-only" for="h{{ $day }}-open">{{ $name }} : ouverture</label>
                        <input class="input min-w-0 px-2" type="time" id="h{{ $day }}-open" name="hours[{{ $day }}][open]" value="{{ old("hours.$day.open", $slot['open'] ?? '08:00') }}">
                        <label class="sr-only" for="h{{ $day }}-close">{{ $name }} : fermeture</label>
                        <input class="input min-w-0 px-2" type="time" id="h{{ $day }}-close" name="hours[{{ $day }}][close]" value="{{ old("hours.$day.close", $slot['close'] ?? '20:00') }}">
                    </div>
                    @error("hours.$day")<p class="error px-3 pb-2">{{ $message }}</p>@enderror
                @endforeach
            </div>
            <p class="help">Décochez un jour pour le fermer. Heures de Conakry.</p>

            <label class="card mt-4 mb-4 flex items-center gap-3 p-4">
                <span class="flex-1">
                    <span class="block font-semibold">Refuser les commandes quand c'est fermé</span>
                    <span class="text-sm text-text-muted">Sinon, les clients commandent à toute heure et la vitrine leur dit quand vous répondrez.</span>
                </span>
                <span class="switch"><input type="checkbox" role="switch" name="closed_outside_hours" value="1" @checked(old('closed_outside_hours', $store->closed_outside_hours))><span></span></span>
            </label>
        </div>

        <button class="btn btn-primary btn-lg mt-4" type="submit">Enregistrer</button>
    </form>
</x-layouts.admin>
