<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMethod;
use App\Models\Store;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * S18 : modes de livraison. Frais, minimum de commande, et la case
 * « paiement à la livraison possible » par mode (ex. Conakry oui, Kankan non).
 */
class DeliverySettingsController extends Controller
{
    /** Suggestions pour aller vite : communes de Conakry et grandes villes. */
    public const SUGGESTIONS = ['Kaloum', 'Dixinn', 'Matam', 'Ratoma', 'Matoto', 'Kankan', 'Labé', 'Nzérékoré', 'Kindia', 'Boké'];

    public function index(Request $request): View
    {
        $store = $this->currentStore($request);
        $methods = $store->deliveryMethods()->get();
        $used = $methods->pluck('name')->map(fn ($n) => mb_strtolower($n));

        return view('admin.settings.delivery', [
            'methods' => $methods,
            'cod' => $store->cashOnDelivery(),
            'suggestions' => collect(self::SUGGESTIONS)->reject(fn ($s) => $used->contains(fn ($u) => str_contains($u, mb_strtolower($s))))->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $store = $this->currentStore($request);
        $data = $this->validated($request);
        $data['position'] = (int) $store->deliveryMethods()->max('position') + 1;
        $store->deliveryMethods()->create($data);

        return back()->with('status', '« '.$data['name'].' » ajouté.');
    }

    public function update(Request $request, int $method): RedirectResponse
    {
        $store = $this->currentStore($request);
        $model = $store->deliveryMethods()->findOrFail($method);
        $data = $this->validated($request);

        if (! $data['is_active'] && $model->is_active && $store->deliveryMethods()->where('is_active', true)->count() <= 1) {
            return back()->withErrors(['delivery' => 'Gardez au moins un mode de livraison actif, sinon personne ne peut commander.']);
        }

        $model->update($data);

        return back()->with('status', '« '.$model->name.' » enregistré.');
    }

    private function validated(Request $request): array
    {
        $digits = fn ($v) => ($c = preg_replace('/\D+/', '', (string) $v)) === '' ? '0' : $c;
        $request->merge(['fee' => $digits($request->input('fee')), 'min_order' => $digits($request->input('min_order'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in([DeliveryMethod::DELIVERY, DeliveryMethod::PICKUP])],
            'fee' => ['required', 'integer', 'min:0', 'max:100000000'],
            'min_order' => ['required', 'integer', 'min:0', 'max:10000000000'],
        ], ['name.required' => 'Donnez un nom, ex. « Livraison Ratoma ».']);

        return $data + [
            'allows_cash_on_delivery' => $request->boolean('allows_cash_on_delivery'),
            // Case décochée = champ absent = désactivé (le formulaire d'ajout envoie is_active=1).
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function currentStore(Request $request): Store
    {
        return $request->attributes->get('store');
    }
}
