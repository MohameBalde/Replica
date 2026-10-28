<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PushSubscription;
use App\Models\Store;
use App\Models\User;
use App\Services\PushNotifier;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\SubscriptionInterface;
use Minishlink\WebPush\WebPush;
use Tests\TestCase;

/**
 * Alerte « nouvelle commande » : le commerçant l'active sur ses appareils,
 * et chaque commande d'un client l'avertit, même sans le message WhatsApp.
 */
class OrderAlertTest extends TestCase
{
    use RefreshDatabase;

    private const ANDROID = 'Mozilla/5.0 (Linux; Android 14) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36';

    private User $merchant;

    private Store $store;

    /** Service d'envoi factice : garde les messages, et répond « 410 » pour les appareils oubliés. */
    private WebPush $push;

    protected function setUp(): void
    {
        parent::setUp();

        $this->post('/inscription', [
            'name' => 'Aïssatou', 'phone' => '620 11 22 33',
            'password' => 'motdepasse', 'password_confirmation' => 'motdepasse',
        ]);
        $this->post('/admin/boutique/creer', ['name' => 'NimStore', 'slug' => 'nimstore', 'whatsapp_number' => '620 11 22 33', 'city' => 'Conakry']);
        $this->post('/admin/produits', ['name' => 'Baskets', 'price' => '150000'])->assertSessionHasNoErrors();
        $this->merchant = User::where('phone', '224620112233')->firstOrFail();
        $this->store = Store::where('slug', 'nimstore')->firstOrFail();

        $this->push = new class extends WebPush
        {
            /** @var list<array{0: string, 1: ?string}> */
            public array $sent = [];

            /** @var list<string> */
            public array $gone = [];

            public function __construct() {}

            public function queueNotification(SubscriptionInterface $subscription, ?string $payload = null, array $options = [], array $auth = []): void
            {
                $this->sent[] = [$subscription->getEndpoint(), $payload];
            }

            public function flush(?int $batchSize = null): \Generator
            {
                foreach ($this->sent as [$endpoint]) {
                    $gone = in_array($endpoint, $this->gone, true);
                    yield new MessageSentReport(new Request('POST', $endpoint), new Response($gone ? 410 : 201), ! $gone, $gone ? 'Gone' : 'Created');
                }
            }
        };
        $this->app->instance(PushNotifier::class, new PushNotifier($this->push));
    }

    private function subscribe(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): \Illuminate\Testing\TestResponse
    {
        return $this->withHeader('User-Agent', self::ANDROID)->postJson('/admin/alertes', [
            'endpoint' => $endpoint, 'expirationTime' => null,
            'keys' => ['p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM', 'auth' => 'tBHItJI5svbpez7KI4CCXg'],
        ]);
    }

    private function customerOrder(): Order
    {
        auth()->logout();
        $variant = $this->store->products()->firstOrFail()->variants()->firstOrFail();
        $this->post('/nimstore/panier', ['variant_id' => $variant->id]);
        $this->post('/nimstore/commande', [
            'name' => 'Mariama', 'phone' => '621 00 00 01',
            'delivery_method_id' => $this->store->deliveryMethods()->where('type', 'pickup')->value('id'),
            'payment_method_id' => $this->store->cashOnDelivery()->id,
        ])->assertSessionHasNoErrors()->assertRedirect();

        return $this->store->orders()->latest('id')->firstOrFail();
    }

    public function test_dashboard_offers_to_turn_alerts_on(): void
    {
        $this->get('/admin')->assertOk()
            ->assertSee('data-push', false)
            ->assertSee('data-key="'.config('wanllan.vapid.public').'"', false)
            ->assertSee('Activer les alertes');
    }

    public function test_merchant_turns_alerts_on_and_off_per_device(): void
    {
        $this->subscribe()->assertOk()->assertJson(['devices' => 1]);
        $this->subscribe()->assertOk()->assertJson(['devices' => 1]); // même appareil : pas de doublon
        $this->subscribe('https://updates.push.services.mozilla.com/wpush/v2/xyz')->assertJson(['devices' => 2]);
        $this->assertSame('Android · Chrome', PushSubscription::firstOrFail()->device);

        $this->deleteJson('/admin/alertes', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123'])->assertOk()->assertJson(['devices' => 1]);
    }

    public function test_only_https_push_addresses_are_accepted(): void
    {
        $this->subscribe('http://exemple.test/push')->assertUnprocessable();
        $this->postJson('/admin/alertes', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc'])->assertUnprocessable();
        $this->assertSame(0, PushSubscription::count());
    }

    public function test_a_device_follows_the_last_account_signed_in(): void
    {
        $this->subscribe()->assertOk();
        auth()->logout();
        $this->post('/inscription', [
            'name' => 'Souleymane', 'phone' => '621 99 88 77',
            'password' => 'motdepasse', 'password_confirmation' => 'motdepasse',
        ]);
        $this->post('/admin/boutique/creer', ['name' => 'Nimaga', 'slug' => 'nimaga', 'whatsapp_number' => '621 99 88 77', 'city' => 'Conakry']);
        $this->subscribe()->assertOk();

        $this->assertSame(1, PushSubscription::count());
        $this->assertSame(0, $this->merchant->pushSubscriptions()->count());
        // Un commerçant ne peut pas retirer l'appareil d'un autre.
        $this->actingAs($this->merchant)->deleteJson('/admin/alertes', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123']);
        $this->assertSame(1, PushSubscription::count());
    }

    public function test_customer_order_alerts_the_merchant_devices(): void
    {
        $this->subscribe()->assertOk();
        $order = $this->customerOrder();

        $this->assertCount(1, $this->push->sent);
        [$endpoint, $payload] = $this->push->sent[0];
        $this->assertSame('https://fcm.googleapis.com/fcm/send/abc123', $endpoint);
        $message = json_decode($payload, true);
        $this->assertSame('Nouvelle commande · '.gnf($order->total), $message['title']);
        $this->assertStringContainsString('Mariama', $message['body']);
        $this->assertSame(route('admin.orders.show', $order->id), $message['url']);
    }

    public function test_orders_without_alerts_on_send_nothing(): void
    {
        $this->customerOrder();
        $this->assertSame([], $this->push->sent);
    }

    public function test_devices_that_refuse_alerts_are_forgotten(): void
    {
        $this->subscribe()->assertOk();
        $this->subscribe('https://updates.push.services.mozilla.com/wpush/v2/xyz')->assertOk();
        $this->push->gone = ['https://updates.push.services.mozilla.com/wpush/v2/xyz'];

        $this->postJson('/admin/alertes/test')->assertOk()->assertJson(['sent' => 1]);
        $this->assertSame(['https://fcm.googleapis.com/fcm/send/abc123'], PushSubscription::pluck('endpoint')->all());
    }

    public function test_a_failing_push_service_never_breaks_the_order(): void
    {
        $this->subscribe()->assertOk();
        $this->app->instance(PushNotifier::class, new PushNotifier(new class extends WebPush
        {
            public function __construct() {}

            public function queueNotification(SubscriptionInterface $subscription, ?string $payload = null, array $options = [], array $auth = []): void
            {
                throw new \RuntimeException('service injoignable');
            }
        }));

        $this->customerOrder();
        $this->assertSame(1, Order::count());
    }

    public function test_keys_are_created_once_when_none_are_configured(): void
    {
        config(['wanllan.vapid.public' => null, 'wanllan.vapid.private' => null]);
        $path = storage_path('app/private/vapid.json');
        $backup = is_file($path) ? file_get_contents($path) : null;
        @unlink($path);

        try {
            $first = PushNotifier::keys();
            $this->assertSame($first, PushNotifier::keys());
            $this->assertSame(87, strlen($first['publicKey']));
        } finally {
            @unlink($path);
            if ($backup !== null) {
                file_put_contents($path, $backup);
            }
        }
    }
}
