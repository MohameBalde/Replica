<?php

namespace Tests\Feature;

use App\Models\DailyVisit;
use App\Models\Order;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private Store $store;

    private Product $shoes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->post('/inscription', [
            'name' => 'Aïssatou', 'phone' => '620 11 22 33',
            'password' => 'motdepasse', 'password_confirmation' => 'motdepasse',
        ]);
        $this->post('/admin/boutique/creer', ['name' => 'NimStore', 'slug' => 'nimstore', 'whatsapp_number' => '620 11 22 33', 'city' => 'Conakry']);
        $this->merchant = User::where('phone', '224620112233')->firstOrFail();
        $this->store = Store::where('slug', 'nimstore')->firstOrFail();

        $this->actingAs($this->merchant)->post('/admin/produits', [
            'name' => 'Baskets', 'price' => '150000',
            'variants' => [['name' => '42', 'price' => '', 'stock' => '5'], ['name' => '43', 'price' => '', 'stock' => '0']],
        ])->assertSessionHasNoErrors();
        $this->shoes = $this->store->products()->with('variants')->firstOrFail();
    }

    private function variant(string $name): int
    {
        return $this->shoes->variants->firstWhere('name', $name)->id;
    }

    private function delivery(): int
    {
        return $this->store->deliveryMethods()->create(['name' => 'Livraison Conakry', 'type' => 'delivery', 'fee' => 25000, 'is_active' => false])->id;
    }

    private function submit(array $overrides = [])
    {
        return $this->actingAs($this->merchant)->from('/admin/commandes/nouvelle')->post('/admin/commandes', $overrides + [
            'name' => 'Mariama Bah', 'phone' => '622 33 44 55', 'address' => 'Ratoma, Kipé',
            'delivery_method_id' => $this->store->deliveryMethods()->where('type', 'pickup')->value('id'),
            'payment_method_id' => $this->store->cashOnDelivery()->id,
            'status' => 'confirmed',
            'items' => [['variant_id' => $this->variant('42'), 'quantity' => 2, 'price' => '']],
        ]);
    }

    public function test_form_lists_every_variant_with_price_and_stock_and_is_linked_from_the_list(): void
    {
        $this->actingAs($this->merchant)->get('/admin/commandes')->assertSee(route('admin.orders.create'), false)->assertSee('Nouvelle commande');

        $html = $this->actingAs($this->merchant)->get('/admin/commandes/nouvelle')->assertOk()
            ->assertSee('Baskets (42) · 150 000 GNF · 5 en stock')
            ->assertSee('Baskets (43) · 150 000 GNF · épuisé')
            ->assertSee('Paiement à la livraison')
            ->assertSeeInOrder(['Confirmée : à préparer ou à livrer', 'Terminée : déjà remise au client', 'En attente : à confirmer plus tard'])
            ->getContent();
        $this->assertMatchesRegularExpression('/value="'.$this->variant('43').'" data-price="150000"\s+disabled/', $html); // épuisé : pas sélectionnable
        $this->assertDoesNotMatchRegularExpression('/value="'.$this->variant('42').'" data-price="150000"\s+disabled/', $html);
    }

    public function test_a_phone_order_takes_a_number_the_stock_and_lands_on_its_page(): void
    {
        $deliveryId = $this->delivery(); // masquée en ligne, mais le commerçant peut la choisir
        $response = $this->submit([
            'delivery_method_id' => $deliveryId,
            'items' => [
                ['variant_id' => $this->variant('42'), 'quantity' => 2, 'price' => '120 000'], // prix négocié
                ['variant_id' => '', 'quantity' => 1, 'price' => ''],                            // ligne vide ignorée
            ],
            'internal_note' => 'Livrer après 17 h',
        ]);

        $order = $this->store->orders()->latest('id')->firstOrFail();
        $response->assertRedirect(route('admin.orders.show', $order->id))->assertSessionHas('status', 'Commande #1001 enregistrée.');

        $this->assertSame([1001, 'confirmed', 'unpaid', 'unfulfilled', 'manual'], [$order->number, $order->status, $order->payment_status, $order->fulfillment_status, $order->source]);
        $this->assertSame([240000, 25000, 265000], [$order->subtotal, $order->delivery_fee, $order->total]);
        $this->assertSame(['224622334455', 'Ratoma, Kipé', 'Livrer après 17 h'], [$order->customer_phone, $order->address, $order->internal_note]);
        $this->assertNotNull($order->whatsapp_sent_at);
        $this->assertSame([120000, 2, 240000], [$order->items[0]->unit_price, $order->items[0]->quantity, $order->items[0]->line_total]);
        $this->assertSame(3, $this->shoes->variants->firstWhere('name', '42')->fresh()->stock);
        $this->assertSame(-2, StockMovement::where('order_id', $order->id)->value('delta'));
        $this->assertSame('Mariama Bah', $this->store->customers()->where('phone', '224622334455')->value('name'));

        $this->actingAs($this->merchant)->get(route('admin.orders.show', $order->id))
            ->assertSee('saisie à la main')->assertSee('Note interne :')->assertSee('Livrer après 17 h')
            ->assertDontSee('Le client n\'a pas encore envoyé le message WhatsApp.');
        $this->actingAs($this->merchant)->get('/admin/commandes')->assertSee('saisie à la main')->assertDontSee('WhatsApp non envoyé');
    }

    public function test_a_counter_sale_is_paid_and_done_and_counts_in_the_sales(): void
    {
        $this->submit(['status' => 'completed', 'paid' => '1', 'address' => '']);
        $order = $this->store->orders()->firstOrFail();

        $this->assertSame(['completed', 'paid', 'fulfilled', null], [$order->status, $order->payment_status, $order->fulfillment_status, $order->address]);
        $this->actingAs($this->merchant)->get('/admin')->assertSee('Vos ventes')->assertSee('300 000')
            ->assertDontSee('Le plus de commandes vient de'); // une saisie n'est pas un réseau
    }

    public function test_not_enough_stock_saves_nothing_and_keeps_the_number(): void
    {
        $this->submit(['items' => [['variant_id' => $this->variant('42'), 'quantity' => 6, 'price' => '']]])
            ->assertRedirect('/admin/commandes/nouvelle')->assertSessionHasErrors(['items' => '« Baskets » : plus que 5 en stock.']);
        $this->submit(['items' => [['variant_id' => $this->variant('43'), 'quantity' => 1, 'price' => '']]])
            ->assertSessionHasErrors(['items' => '« Baskets » vient d\'être épuisé.']);

        $this->assertSame(0, $this->store->orders()->count());
        $this->assertSame(1001, $this->store->fresh()->next_order_number);
        $this->assertSame(5, $this->shoes->variants->firstWhere('name', '42')->fresh()->stock);
    }

    public function test_delivery_needs_an_address_pickup_keeps_none_and_payment_needs_a_number(): void
    {
        $this->submit(['delivery_method_id' => $this->delivery(), 'address' => ''])->assertSessionHasErrors(['address' => 'Indiquez l\'adresse de livraison.']);

        $mtn = $this->store->paymentMethods()->where('type', 'mtn_momo')->firstOrFail(); // créé sans numéro
        $this->assertFalse($mtn->isComplete());
        $this->submit(['payment_method_id' => $mtn->id])->assertSessionHasErrors('payment_method_id');
        $this->assertSame(0, Order::count());
        $this->actingAs($this->merchant)->get('/admin/commandes/nouvelle')->assertSee('MTN MoMo (numéro à ajouter)');

        $this->submit(['payment_method_id' => $mtn->id, 'paid' => '1'])->assertSessionHasNoErrors(); // déjà payé : pas besoin du numéro
        $this->assertNull(Order::firstOrFail()->address);                                            // retrait : l'adresse saisie n'est pas gardée
    }

    public function test_the_same_article_on_two_lines_reports_the_real_stock(): void
    {
        $this->submit(['items' => [
            ['variant_id' => $this->variant('42'), 'quantity' => 4, 'price' => ''],
            ['variant_id' => $this->variant('42'), 'quantity' => 3, 'price' => '100 000'],
        ]])->assertSessionHasErrors(['items' => '« Baskets » : plus que 5 en stock.']);
        $this->assertSame(5, $this->shoes->variants->firstWhere('name', '42')->fresh()->stock);
    }

    public function test_a_counter_sale_customer_page_does_not_ask_to_pay_or_send_again(): void
    {
        $this->submit(['status' => 'completed', 'paid' => '1']);
        $order = Order::firstOrFail();

        auth()->logout();
        $this->get(route('invoice.show', $order))->assertOk()->assertSee('Merci pour votre commande !')
            ->assertDontSee('Dernière étape')->assertDontSee('en espèces à la réception')->assertDontSee(route('invoice.whatsapp', $order), false);
    }

    public function test_hand_entered_orders_are_not_beaten_by_one_network_order(): void
    {
        $this->submit();
        $this->submit(['phone' => '622 33 44 56']);
        $this->post('/nimstore/panier', ['variant_id' => $this->variant('42'), 'quantity' => 1]);
        auth()->logout();
        $this->get('/nimstore?src=tiktok');
        $this->post('/nimstore/panier', ['variant_id' => $this->variant('42'), 'quantity' => 1]);
        $this->post('/nimstore/commande', [
            'name' => 'Client TikTok', 'phone' => '621 00 00 07',
            'delivery_method_id' => $this->store->deliveryMethods()->where('type', 'pickup')->value('id'),
            'payment_method_id' => $this->store->cashOnDelivery()->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->merchant)->get('/admin')->assertSee('Vos ventes')->assertDontSee('Le plus de commandes vient de TikTok');
    }

    public function test_another_stores_items_or_methods_are_refused(): void
    {
        auth()->logout();
        $this->post('/inscription', ['name' => 'Autre', 'phone' => '622 00 00 00', 'password' => 'motdepasse', 'password_confirmation' => 'motdepasse']);
        $this->post('/admin/boutique/creer', ['name' => 'Autre', 'slug' => 'autre', 'whatsapp_number' => '622 00 00 00', 'city' => 'Labé']);
        $this->post('/admin/produits', ['name' => 'Secret', 'price' => '1000'])->assertSessionHasNoErrors();
        $otherStore = Store::where('slug', 'autre')->firstOrFail();
        $theirVariant = $otherStore->products()->firstOrFail()->variants()->firstOrFail();
        $theirPayment = $otherStore->cashOnDelivery();

        $this->submit(['items' => [['variant_id' => $theirVariant->id, 'quantity' => 1, 'price' => '']]])
            ->assertSessionHasErrors(['items' => 'Un article choisi n\'existe plus. Rechargez la page.']);
        $this->submit(['payment_method_id' => $theirPayment->id])->assertSessionHasErrors(['payment_method_id' => 'Choisissez un moyen de paiement.']);
        $this->assertSame(0, Order::count());
    }

    public function test_missing_or_wrong_fields_get_a_clear_message(): void
    {
        $this->submit(['items' => [['variant_id' => '', 'quantity' => 1, 'price' => '']]])->assertSessionHasErrors(['items' => 'Ajoutez au moins un article.']);
        $this->submit(['phone' => '12'])->assertSessionHasErrors(['phone' => 'Entrez un numéro guinéen valable, par exemple 620 00 00 00.']);
        $this->submit(['name' => ''])->assertSessionHasErrors(['name' => 'Indiquez le nom du client.']);
        $this->submit(['status' => 'cancelled'])->assertSessionHasErrors('status');
        $this->submit(['items' => [['variant_id' => $this->variant('42'), 'quantity' => 0, 'price' => '']]])->assertSessionHasErrors('items.0.quantity');
        $this->assertSame(0, Order::count());

        auth()->logout();
        $this->post('/admin/commandes', ['name' => 'X'])->assertRedirect('/connexion');
        $this->get('/admin/commandes/nouvelle')->assertRedirect('/connexion');
    }

    public function test_manual_is_not_a_link_source(): void
    {
        $this->get('/nimstore?src=manual');
        $this->assertSame('direct', DailyVisit::where('store_id', $this->store->id)->value('source'));
    }
}
