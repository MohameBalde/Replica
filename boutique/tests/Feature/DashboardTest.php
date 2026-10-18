<?php

namespace Tests\Feature;

use App\Models\DailyVisit;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Support\Period;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(10, 0));

        $this->post('/inscription', [
            'name' => 'Aïssatou', 'phone' => '620 11 22 33',
            'password' => 'motdepasse', 'password_confirmation' => 'motdepasse',
        ]);
        $this->post('/admin/boutique/creer', ['name' => 'NimStore', 'slug' => 'nimstore', 'whatsapp_number' => '620 11 22 33', 'city' => 'Conakry']);
        $this->merchant = User::where('phone', '224620112233')->firstOrFail();
        $this->store = Store::where('slug', 'nimstore')->firstOrFail();
    }

    private function addProduct(string $name, int $price = 150000, ?int $stock = null, array $variants = [], bool $visible = true): Product
    {
        $this->actingAs($this->merchant)->post('/admin/produits', [
            'name' => $name, 'price' => (string) $price, 'stock' => $stock === null ? '' : (string) $stock,
            'variants' => $variants,
        ])->assertSessionHasNoErrors();
        $product = $this->store->products()->where('name', $name)->with('variants')->firstOrFail();
        if (! $visible) {
            $product->update(['is_visible' => false]); // un nouveau produit est toujours visible
        }

        return $product;
    }

    /** Une commande par le vrai parcours client : vitrine (source), panier, commande. */
    private function order(Product $product, int $qty = 1, ?string $src = null, ?int $paymentMethodId = null, string $phone = '621 00 00 01'): Order
    {
        if ($src) {
            $this->get('/nimstore?src='.$src);
        }
        $this->post('/nimstore/panier', ['variant_id' => $product->variants->first()->id, 'quantity' => $qty]);
        $this->post('/nimstore/commande', [
            'name' => 'Client '.$phone, 'phone' => $phone,
            'delivery_method_id' => $this->store->deliveryMethods()->where('type', 'pickup')->value('id'),
            'payment_method_id' => $paymentMethodId ?? $this->store->cashOnDelivery()->id,
        ])->assertSessionHasNoErrors();

        return $this->store->orders()->latest('id')->firstOrFail();
    }

    private function setStatuses(Order $order, string $status, string $payment, string $fulfillment = 'unfulfilled'): void
    {
        $this->actingAs($this->merchant)->patch("/admin/commandes/{$order->id}/statuts", [
            'status' => $status, 'payment_status' => $payment, 'fulfillment_status' => $fulfillment,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([$status, $payment], [$order->fresh()->status, $order->fresh()->payment_status]);
    }

    private function cancel(Order $order): void
    {
        $this->actingAs($this->merchant)->post("/admin/commandes/{$order->id}/annuler")->assertRedirect()->assertSessionHasNoErrors();
    }

    private function enableOrangeMoney(bool $active = true): int
    {
        $this->actingAs($this->merchant)->put('/admin/paiement/orange_money', array_filter([
            'account_number' => '620 99 88 77', 'account_name' => 'Aïssatou', 'is_active' => $active ? '1' : null,
        ]))->assertSessionHasNoErrors();

        return $this->store->paymentMethods()->where('type', 'orange_money')->value('id');
    }

    /** Le texte lu à l'écran : sans balises, espaces regroupés (« 1 » et « commande reçue » sont dans deux <p>). */
    private function text(TestResponse $response): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($response->getContent()), ENT_QUOTES)));
    }

    private function assertText(TestResponse $response, string ...$needles): TestResponse
    {
        $text = $this->text($response);
        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $text);
        }

        return $response;
    }

    private function home(string $query = ''): TestResponse
    {
        return $this->actingAs($this->merchant)->get('/admin'.$query)->assertOk();
    }

    public function test_new_store_shows_checklist_and_no_money_tiles(): void
    {
        $this->home()->assertSee('Bien démarrer')->assertSee('1 étape sur 5')->assertSee('Ajouter un produit')
            ->assertDontSee('Vos ventes')->assertDontSee('GNF déjà payés')->assertDontSee('0 GNF');
    }

    public function test_checklist_steps_tick_from_real_data(): void
    {
        $this->addProduct('Baskets');
        $this->home()->assertSee('2 étapes sur 5')->assertSee('Ajouter mon numéro');

        $this->enableOrangeMoney();
        $this->home()->assertSee('3 étapes sur 5')->assertSee('Partager sur WhatsApp')->assertSee('wa.me/?text=', false);

        // Une visite directe (le commerçant lui-même) ne coche pas « Partagez ».
        $this->flushSession();
        $this->get('/nimstore');
        $this->home()->assertSee('3 étapes sur 5')->assertSee('ouverte 1 fois');

        // Une visite venue du lien partagé, si.
        $this->flushSession();
        $this->get('/nimstore?src=whatsapp');
        $this->home()->assertSee('4 étapes sur 5')->assertSee('Voir ma boutique comme un client');
    }

    public function test_mobile_money_step_needs_an_active_method(): void
    {
        $this->enableOrangeMoney(active: false);
        $this->assertNotNull($this->store->paymentMethods()->where('type', 'orange_money')->value('account_number'));
        $this->home()->assertSee('1 étape sur 5');
    }

    public function test_hidden_products_do_not_tick_the_product_step(): void
    {
        $this->addProduct('Caché', visible: false);
        $this->home()->assertSee('1 étape sur 5')->assertSee('Vos produits sont masqués');
    }

    public function test_checklist_survives_a_cancelled_test_order_and_leaves_after_a_real_one(): void
    {
        $product = $this->addProduct('Baskets');
        $this->cancel($this->order($product));
        $this->home()->assertSee('Bien démarrer');

        $this->order($product, phone: '621 00 00 02');
        $this->assertText($this->home(), '1 commande en attente')->assertDontSee('Bien démarrer')->assertSee('Vos ventes');
    }

    public function test_cancelled_and_refunded_orders_are_not_counted(): void
    {
        $product = $this->addProduct('Baskets', 100000);
        $this->order($product);
        $this->cancel($this->order($product, phone: '621 00 00 02'));
        $this->setStatuses($this->order($product, phone: '621 00 00 03'), 'completed', 'refunded');

        $this->assertText($this->home(), '1 commande reçue', 'Valeur des commandes : 100 000 GNF');
    }

    public function test_paid_and_unpaid_split_adds_up_to_the_order_value(): void
    {
        $product = $this->addProduct('Baskets', 100000);
        $this->setStatuses($this->order($product), 'completed', 'paid');
        $this->order($product, 2, phone: '621 00 00 02');

        $this->assertText($this->home(), '2 commandes reçues', '100 000 GNF déjà payés', '200 000 GNF pas encore payés',
            'Valeur des commandes : 300 000 GNF',            // valeur = payés + pas encore payés
            'Commande payée ? Ouvrez-la');
    }

    public function test_partially_paid_orders_count_in_full_with_a_note(): void
    {
        $product = $this->addProduct('Baskets', 100000);
        $this->setStatuses($this->order($product), 'confirmed', 'partially_paid');

        $this->assertText($this->home(), '0 GNF déjà payés', '100 000 GNF pas encore payés', 'payée en partie, comptée en entier');
    }

    public function test_periods_use_conakry_days(): void
    {
        $product = $this->addProduct('Baskets');
        $this->travelTo(now()->setDate(2026, 10, 11)->setTime(23, 59));
        $this->order($product);
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(0, 0));
        $this->order($product, phone: '621 00 00 02');
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(10, 0));

        $this->assertText($this->home('?periode=7j'), '1 commande reçue', 'du 12/10 au 18/10');
        $this->assertText($this->home('?periode=30j'), '2 commandes reçues', 'du 19/09 au 18/10');
        $this->assertText($this->home('?periode=aujourdhui'), '0 commande reçue', 'Pas encore de commande aujourd\'hui.', 'aujourd\'hui (18/10)');
        $this->assertText($this->home('?periode=xyz'), '1 commande reçue', 'du 12/10 au 18/10');
    }

    public function test_visits_tile_sums_the_window(): void
    {
        $this->order($this->addProduct('Baskets'));
        $today = Period::today();
        foreach ([[$today, 'tiktok', 3], [now()->subDays(6)->toDateString(), 'direct', 2], [now()->subDays(7)->toDateString(), 'whatsapp', 5]] as [$day, $src, $n]) {
            DailyVisit::create(['store_id' => $this->store->id, 'day' => $day, 'source' => $src, 'visits' => $n]);
        }

        $this->assertText($this->home('?periode=aujourdhui'), '3 visites de la boutique');
        $this->assertText($this->home('?periode=7j'), '5 visites de la boutique')->assertDontSee('personnes');
        $this->assertText($this->home('?periode=30j'), '10 visites de la boutique');
    }

    public function test_todo_counts_match_the_lists_they_open(): void
    {
        $product = $this->addProduct('Baskets');
        $om = $this->enableOrangeMoney();
        $a = $this->order($product, paymentMethodId: $om);
        $b = $this->order($product, paymentMethodId: $om, phone: '621 00 00 02');
        Order::whereKey([$a->id, $b->id])->update(['payment_status' => 'confirming']);
        $this->cancel($b);
        $c = $this->order($product, phone: '621 00 00 03');              // à la livraison, active
        $this->cancel($this->order($product, phone: '621 00 00 04'));     // à la livraison, annulée

        $this->assertText($this->home(), '1 paiement à vérifier', '2 commandes en attente', '1 à encaisser à la livraison · 150 000 GNF')
            ->assertSee('paiement=confirming', false)->assertSee('statut=pending', false)->assertSee('paiement=cod', false);

        $list = fn (string $q) => $this->actingAs($this->merchant)->get('/admin/commandes'.$q)->assertOk();
        $list('?paiement=confirming')->assertSee('#'.$a->number)->assertDontSee('#'.$b->number);
        $list('?paiement=cod')->assertSee('#'.$c->number)->assertDontSee('#1004');
        $list('?statut=pending')->assertSee('#'.$a->number)->assertSee('#'.$c->number)->assertDontSee('#'.$b->number);
    }

    public function test_paid_filter_still_lists_cancelled_paid_orders(): void
    {
        $order = $this->order($this->addProduct('Baskets'));
        $this->setStatuses($order, 'confirmed', 'paid');
        $this->cancel($order);

        $this->actingAs($this->merchant)->get('/admin/commandes?paiement=paid')->assertSee('#'.$order->number);
    }

    public function test_cash_on_delivery_line_flags_completed_unpaid_orders(): void
    {
        $this->setStatuses($this->order($this->addProduct('Baskets')), 'completed', 'unpaid');
        $this->home()->assertSee('dont 1 terminée');
    }

    public function test_pending_orders_hint_whatsapp_then_age(): void
    {
        $this->order($this->addProduct('Baskets'));
        $this->home()->assertSee('n\'a pas ouvert WhatsApp', false)->assertSee('WhatsApp non envoyé');

        $this->travel(3)->days();
        $this->home()->assertSee('depuis plus de 2 jours');
    }

    public function test_low_stock_is_listed_per_variant(): void
    {
        $this->addProduct('Survêtement', 250000, variants: [
            ['name' => 'M', 'stock' => '2'], ['name' => 'L', 'stock' => '1'], ['name' => 'XL', 'stock' => '0'],
        ]);
        $this->addProduct('Parfum');                     // stock non suivi
        $this->addProduct('Caché', stock: 1, visible: false);
        $this->addProduct('Casquette', stock: 4);

        $this->home()->assertSee('Stock bas')
            ->assertSeeInOrder(['Survêtement (XL)', 'Épuisé', 'Survêtement (L)', 'Plus que 1', 'Survêtement (M)', 'Plus que 2'])
            ->assertDontSee('Parfum')->assertDontSee('Caché')->assertDontSee('Casquette');

        $this->actingAs($this->merchant)->get('/admin/produits')->assertSee('Plus que 3')->assertSee('4 en stock');
    }

    public function test_top_products_count_quantities_under_the_current_name(): void
    {
        $shoes = $this->addProduct('Baskets');
        $cap = $this->addProduct('Casquette', 50000);
        $this->order($shoes, 2);
        $this->order($cap, 1, phone: '621 00 00 02');
        $this->actingAs($this->merchant)->put("/admin/produits/{$shoes->id}", ['name' => 'Baskets blanches', 'price' => '150000', 'is_visible' => '1'])
            ->assertSessionHasNoErrors();

        $this->home()->assertSee('Ce qui se vend')
            ->assertSeeInOrder(['Baskets blanches', '2 commandés', 'Casquette', '1 commandé']);
    }

    public function test_one_store_never_sees_another_stores_numbers(): void
    {
        $this->order($this->addProduct('Baskets', 100000));

        $other = User::factory()->create();
        $otherStore = Store::forceCreate(['user_id' => $other->id, 'name' => 'Autre', 'slug' => 'autre', 'whatsapp_number' => '224622000000', 'city' => 'Conakry']);
        $otherStore->orders()->create([
            'number' => 5001, 'public_token' => (string) \Illuminate\Support\Str::ulid(), 'customer_name' => 'Client Secret', 'customer_phone' => '224622111111',
            'delivery_method_name' => 'Retrait', 'payment_method_type' => 'cash_on_delivery', 'subtotal' => 900000, 'total' => 900000,
        ]);
        DailyVisit::create(['store_id' => $otherStore->id, 'day' => Period::today(), 'source' => 'tiktok', 'visits' => 77]);

        $this->assertText($this->home(), '1 commande reçue', '0 visite de la boutique')
            ->assertDontSee('Client Secret')->assertDontSee('900 000')->assertDontSee('77');
    }

    public function test_closed_store_banner_reopens_from_home(): void
    {
        $this->actingAs($this->merchant)->post('/admin/reglages/ouverture');
        $this->assertFalse($this->store->fresh()->is_open);

        $this->home()->assertSee('Votre boutique est fermée')->assertSee('Ouvrir ma boutique');
        $this->from('/admin')->post('/admin/reglages/ouverture')->assertRedirect('/admin')->assertSessionHas('status', 'Boutique ouverte : vous recevez des commandes.');
    }

    public function test_home_is_where_merchants_land(): void
    {
        $this->home();
        $this->actingAs($this->merchant)->get('/')->assertRedirect(route('admin.home'));
        $this->actingAs($this->merchant)->get('/admin/boutique/creer')->assertRedirect(route('admin.home'));

        auth()->logout();
        $this->post('/connexion', ['phone' => '620 11 22 33', 'password' => 'motdepasse'])->assertRedirect(route('admin.home'));

        $this->actingAs(User::factory()->create())->get('/admin')->assertRedirect(route('admin.store.create'));

        $this->store->suspend('Test');
        $this->actingAs($this->merchant)->get('/admin')->assertForbidden();
    }

    public function test_menu_has_five_entries_with_home_first(): void
    {
        $this->home()->assertSeeInOrder(['Accueil', 'Commandes', 'Produits', 'Partager', 'Réglages']);
    }

    public function test_day_labels_do_not_depend_on_the_app_language(): void
    {
        $this->order($this->addProduct('Baskets'));
        app()->setLocale('en');
        $this->home()->assertSee('Auj.')->assertDontSee('>Sun<', false)->assertDontSee('>Mon<', false);
    }

    public function test_home_stays_within_a_small_query_budget(): void
    {
        $product = $this->addProduct('Survêtement', 250000, variants: [['name' => 'M', 'stock' => '30'], ['name' => 'L', 'stock' => '2']]);
        $this->enableOrangeMoney();
        foreach (range(1, 6) as $i) {
            $this->order($product, $i % 3 + 1, ['tiktok', 'whatsapp', null][$i % 3], phone: '621 00 00 0'.$i);
        }

        $this->actingAs($this->merchant);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/admin')->assertOk()->assertSee('Vos ventes');
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(10, $queries, "L'Accueil a fait {$queries} requêtes.");
    }

    public function test_share_page_uses_the_same_window_and_rule(): void
    {
        $product = $this->addProduct('Baskets', 100000);
        $this->travelTo(now()->subDays(7));
        $this->order($product, src: 'tiktok');                  // il y a 7 jours : hors de « 7 derniers jours »
        $this->travelBack();
        $this->travelTo(now()->setDate(2026, 10, 18)->setTime(10, 0));
        $this->flushSession();
        $this->setStatuses($this->order($product, src: 'facebook', phone: '621 00 00 02'), 'completed', 'refunded');

        $this->actingAs($this->merchant)->get('/admin/partager')->assertOk()
            ->assertSeeInOrder(['Facebook</td>', '1</td>', '0</td>', '0 GNF</td>'], false)  // la visite compte, pas la commande remboursée
            ->assertDontSee('TikTok</td>', false)
            ->assertDontSee('100 000 GNF</td>', false);
    }
}
