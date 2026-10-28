<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PushSubscription;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;

/**
 * Alerte « nouvelle commande » sur les appareils du commerçant (notifications web, gratuites,
 * sans Meta ni SMS). Le commerçant est prévenu même si le client n'appuie pas sur « Envoyer »
 * dans WhatsApp. Une alerte qui échoue ne bloque jamais une commande.
 */
class PushNotifier
{
    /** @param WebPush|null $client remplacé dans les tests */
    public function __construct(private ?WebPush $client = null) {}

    /**
     * Clés VAPID : celles du .env si présentes, sinon une paire créée au premier besoin et gardée
     * dans storage (partagé entre les versions du site). Changer de clés oblige à réactiver les alertes.
     *
     * @return array{publicKey: string, privateKey: string}
     */
    public static function keys(): array
    {
        if (config('wanllan.vapid.public') && config('wanllan.vapid.private')) {
            return ['publicKey' => config('wanllan.vapid.public'), 'privateKey' => config('wanllan.vapid.private')];
        }

        $path = storage_path('app/private/vapid.json');
        if (! is_file($path)) {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0700, true);
            }
            // Création exclusive : si deux requêtes arrivent en même temps, une seule paire est gardée.
            if ($handle = @fopen($path, 'x')) {
                fwrite($handle, json_encode(VAPID::createVapidKeys()));
                fclose($handle);
                @chmod($path, 0600);
            }
        }

        return json_decode((string) file_get_contents($path), true);
    }

    public static function publicKey(): string
    {
        return self::keys()['publicKey'];
    }

    public function newOrder(Order $order): int
    {
        $order->loadMissing('store');

        return $this->send($order->store->user_id, [
            'title' => 'Nouvelle commande · '.gnf($order->total),
            'body' => 'N° '.$order->number.' de '.$order->customer_name.'. Touchez pour la voir.',
            'url' => route('admin.orders.show', $order->id),
            'tag' => 'commande-'.$order->id,
        ]);
    }

    public function test(User $user): int
    {
        return $this->send($user->id, [
            'title' => 'Alertes Wanllan activées ✅',
            'body' => 'Vous serez prévenu ici à chaque nouvelle commande.',
            'url' => route('admin.home'),
            'tag' => 'test',
        ]);
    }

    /** @return int nombre d'appareils prévenus */
    private function send(int $userId, array $message): int
    {
        $subscriptions = PushSubscription::where('user_id', $userId)->get();
        if ($subscriptions->isEmpty()) {
            return 0;
        }

        try {
            return $this->deliver($subscriptions, json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (\Throwable $e) {
            Log::warning('Alerte « nouvelle commande » non envoyée : '.$e->getMessage());

            return 0;
        }
    }

    /** @param Collection<int, PushSubscription> $subscriptions */
    private function deliver(Collection $subscriptions, string $payload): int
    {
        $client = $this->client ?? new WebPush(
            // Contact exigé par les services d'envoi : une adresse https (ou mailto).
            ['VAPID' => ['subject' => str_starts_with((string) config('app.url'), 'https://') ? config('app.url') : 'https://wanllan.com'] + self::keys()],
            ['TTL' => 12 * 3600, 'urgency' => 'high'],
            new Client(['timeout' => 5, 'connect_timeout' => 3]),
            logger: app('log'), // sinon ses simples avertissements deviennent des erreurs PHP
        );

        foreach ($subscriptions as $subscription) {
            $client->queueNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => 'aes128gcm', // le seul accepté par les iPhone ; Chrome et Firefox aussi
            ]), $payload);
        }

        $sent = 0;
        foreach ($client->flush() as $report) {
            if ($report->isSuccess()) {
                $sent++;
            } elseif ($report->isSubscriptionExpired()) {
                // Alertes désactivées sur l'appareil, ou app désinstallée : on oublie cet appareil.
                PushSubscription::where('endpoint_hash', PushSubscription::hashOf($report->getEndpoint()))->delete();
            } else {
                Log::warning('Alerte non remise : '.$report->getReason());
            }
        }

        return $sent;
    }
}
