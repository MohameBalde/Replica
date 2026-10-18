<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** S20 : infos de la boutique et interrupteur ouvert / fermé. */
class StoreSettingsController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.settings.store', [
            'store' => $this->currentStore($request),
            'cities' => StoreSetupController::CITIES,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $store = $this->currentStore($request);
        $request->merge(['whatsapp_number' => Phone::normalize($request->input('whatsapp_number')) ?? $request->input('whatsapp_number')]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'whatsapp_number' => ['required', 'regex:/^2246\d{8}$/'],
            'city' => ['required', Rule::in(StoreSetupController::CITIES)],
        ], ['whatsapp_number.regex' => 'Entrez un numéro WhatsApp guinéen valable.']);

        $store->update($data + ['is_open' => $request->boolean('is_open')]);

        return back()->with('status', $store->is_open ? 'Boutique enregistrée.' : 'Boutique enregistrée. Elle est fermée : les clients voient les produits mais ne peuvent pas commander.');
    }

    /**
     * Interrupteur rapide. Avec is_open, le bouton dit l'état voulu : un double appui
     * ou une page restée ouverte ne referme pas la boutique qu'on vient d'ouvrir.
     */
    public function toggle(Request $request): RedirectResponse
    {
        $store = $this->currentStore($request);
        $store->update(['is_open' => $request->has('is_open') ? $request->boolean('is_open') : ! $store->is_open]);

        return back()->with('status', $store->is_open ? 'Boutique ouverte : vous recevez des commandes.' : 'Boutique fermée : plus de nouvelles commandes.');
    }

    private function currentStore(Request $request): Store
    {
        return $request->attributes->get('store');
    }
}
