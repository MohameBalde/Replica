<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryMethod;
use App\Models\PaymentMethod;
use App\Models\Store;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StoreSetupController extends Controller
{
    public const CITIES = ['Conakry', 'Kankan', 'Labé', 'Nzérékoré', 'Kindia', 'Boké', 'Mamou', 'Faranah', 'Siguiri', 'Kissidougou'];

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()->store()->exists()) {
            return redirect()->route('admin.home');
        }

        return view('admin.store.create', [
            'cities' => self::CITIES,
            'suggestedPhone' => phone_display($request->user()->phone),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->store()->exists()) {
            return redirect()->route('admin.home');
        }

        $request->merge([
            'slug' => Str::slug((string) $request->input('slug')),
            'whatsapp_number' => Phone::normalize($request->input('whatsapp_number')) ?? $request->input('whatsapp_number'),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9][a-z0-9-]{1,58}[a-z0-9]$/',
                Rule::notIn(Store::RESERVED_SLUGS), Rule::unique('stores', 'slug')],
            'whatsapp_number' => ['required', 'regex:/^2246\d{8}$/'],
            'city' => ['required', Rule::in(self::CITIES)],
        ], [
            'slug.unique' => 'Ce lien est déjà pris. Essayez une autre variante.',
            'slug.not_in' => 'Ce lien est réservé. Choisissez-en un autre.',
            'slug.regex' => 'Utilisez seulement des lettres, des chiffres et des tirets.',
            'whatsapp_number.regex' => 'Entrez un numéro WhatsApp guinéen valable.',
        ]);

        DB::transaction(function () use ($request, $data) {
            $store = new Store($data);
            $store->user()->associate($request->user());
            $store->welcome_message = self::defaultWelcomeMessage($data['name'], $data['slug'], $data['city']);
            $store->save();

            // Réglages de départ : retrait gratuit + paiement à la livraison activé
            // (l'usage en Guinée). Le commerçant peut tout changer ensuite.
            $store->deliveryMethods()->create([
                'name' => 'Retrait en boutique', 'type' => DeliveryMethod::PICKUP, 'fee' => 0, 'position' => 0,
            ]);
            $store->deliveryMethods()->create([
                'name' => 'Livraison à '.$data['city'], 'type' => DeliveryMethod::DELIVERY, 'fee' => 25000, 'position' => 1,
            ]);

            $store->paymentMethods()->create(['type' => PaymentMethod::CASH_ON_DELIVERY, 'is_active' => true, 'position' => 0]);
            $store->paymentMethods()->create(['type' => PaymentMethod::ORANGE_MONEY, 'is_active' => false, 'position' => 1]);
            $store->paymentMethods()->create(['type' => PaymentMethod::MTN_MOMO, 'is_active' => false, 'position' => 2]);
        });

        return redirect()->route('admin.products.create')->with('status', 'Votre boutique est créée ! Ajoutez votre premier produit.');
    }

    /**
     * {lien} est remplacé au moment de copier (Store::welcomeMessageText) : si le
     * domaine change, le message suit sans que le commerçant ait à le réécrire.
     */
    public static function defaultWelcomeMessage(string $name, string $slug, string $city): string
    {
        return "Bonjour 👋 Merci de contacter {$name} !\n"
            ."Voir tous nos produits et commander ici : {lien}\n"
            ."Livraison à {$city}. Paiement à la livraison ou par mobile money.";
    }
}
