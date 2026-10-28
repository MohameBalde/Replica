<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\PushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Alerte « nouvelle commande » : le navigateur du commerçant s'abonne (bouton de l'Accueil),
 * se désabonne, ou demande une alerte d'essai.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url', 'starts_with:https://', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        // Un appareil passé d'un compte à un autre ne prévient que le dernier compte connecté.
        PushSubscription::updateOrCreate(
            ['endpoint_hash' => PushSubscription::hashOf($data['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'device' => self::device((string) $request->userAgent()),
            ],
        );

        return response()->json(['devices' => $request->user()->pushSubscriptions()->count()]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $endpoint = (string) $request->input('endpoint');
        $request->user()->pushSubscriptions()->where('endpoint_hash', PushSubscription::hashOf($endpoint))->delete();

        return response()->json(['devices' => $request->user()->pushSubscriptions()->count()]);
    }

    public function test(Request $request, PushNotifier $notifier): JsonResponse
    {
        return response()->json(['sent' => $notifier->test($request->user())]);
    }

    /** « Android · Chrome », « iPhone · Safari »… pour reconnaître ses appareils. */
    public static function device(string $userAgent): string
    {
        $system = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') => 'iPhone',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') => 'Mac',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Appareil',
        };
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera') => 'Opera',
            str_contains($userAgent, 'SamsungBrowser') => 'Samsung',
            str_contains($userAgent, 'Firefox') => 'Firefox',
            str_contains($userAgent, 'Chrome') => 'Chrome',
            str_contains($userAgent, 'Safari') => 'Safari',
            default => 'navigateur',
        };

        return $system.' · '.$browser;
    }
}
