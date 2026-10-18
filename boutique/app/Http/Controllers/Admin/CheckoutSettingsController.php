<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Support\OpeningHours;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Réglages « Commande » : les questions posées au client avant de commander
 * (ex. point de repère) et les horaires d'ouverture de la boutique.
 */
class CheckoutSettingsController extends Controller
{
    public const MAX_QUESTIONS = 3;

    public function edit(Request $request): View
    {
        return view('admin.settings.checkout', ['store' => $this->currentStore($request)]);
    }

    public function update(Request $request): RedirectResponse
    {
        $store = $this->currentStore($request);
        $request->validate([
            'questions' => ['nullable', 'array', 'max:'.self::MAX_QUESTIONS],
            'questions.*.label' => ['nullable', 'string', 'max:80'],
            'hours' => ['nullable', 'array'],
            'hours.*.open' => ['nullable', 'date_format:H:i'],
            'hours.*.close' => ['nullable', 'date_format:H:i'],
        ], [
            'questions.*.label.max' => 'Une question fait au plus 80 caractères.',
            'hours.*.open.date_format' => 'Heure à écrire comme 08:00.',
            'hours.*.close.date_format' => 'Heure à écrire comme 20:00.',
        ]);

        // « Obligatoire » coché sans question : on le dit au lieu de l'ignorer.
        foreach ((array) $request->input('questions', []) as $i => $q) {
            if (! empty($q['required']) && trim((string) ($q['label'] ?? '')) === '') {
                throw ValidationException::withMessages(["questions.$i.label" => 'Écrivez la question, ou décochez « Obligatoire ».']);
            }
        }

        // Questions : seules les lignes remplies comptent.
        $questions = collect($request->input('questions', []))
            ->map(fn ($q) => ['label' => trim((string) ($q['label'] ?? '')), 'required' => ! empty($q['required'])])
            ->filter(fn ($q) => $q['label'] !== '')
            ->values()->all();

        // Horaires : seulement si le commerçant les active. Un jour décoché = fermé.
        $hours = null;
        if ($request->boolean('use_hours')) {
            $hours = [];
            foreach (array_keys(OpeningHours::DAYS) as $day) {
                $row = $request->input("hours.$day", []);
                if (empty($row['open_day'])) {
                    $hours[$day] = null;

                    continue;
                }
                // Fermeture à 00:00 = minuit, gardée comme « 24:00 » pour que les comparaisons restent simples.
                $close = ($row['close'] ?? null) === '00:00' ? '24:00' : ($row['close'] ?? null);
                if (blank($row['open'] ?? null) || blank($close) || $close <= $row['open']) {
                    throw ValidationException::withMessages(["hours.$day" => OpeningHours::DAYS[$day].' : l\'heure de fermeture doit venir après l\'ouverture.']);
                }
                $hours[$day] = ['open' => $row['open'], 'close' => $close];
            }
            if (! array_filter($hours)) {
                throw ValidationException::withMessages(['hours' => 'Cochez au moins un jour d\'ouverture, ou désactivez les horaires.']);
            }
        }

        $store->update([
            'checkout_questions' => $questions ?: null,
            'opening_hours' => $hours,
            'closed_outside_hours' => $hours !== null && $request->boolean('closed_outside_hours'),
        ]);

        return back()->with('status', 'Réglages de commande enregistrés.');
    }

    private function currentStore(Request $request): Store
    {
        return $request->attributes->get('store');
    }
}
