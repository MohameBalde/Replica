<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Store;
use App\Models\User;
use App\Models\Variant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Inscription + création de boutique par les vrais écrans, puis un produit. */
    private function merchantWithProduct(int $price = 150000, ?int $stock = null): array
    {
        $this->post('/inscription', [
            'name' => 'Mohamed', 'phone' => '620 11 22 33',
            'password' => 'motdepasse', 'password_confirmation' => 'motdepasse',
        ])->assertRedirect(route('admin.store.create'));

        $this->post('/admin/boutique/creer', [
            'name' => 'NimStore', 'slug' => 'NimStore', 'whatsapp_number' => '+224 620 11 22 33', 'city' => 'Conakry',
        ])->assertRedirect(route('admin.products.create'));

        $this->post('/admin/produits', [
            'name' => 'Baskets blanches', 'price' => gnf($price, false), 'stock' => $stock, 'category' => 'Chaussures',
        ])->assertRedirect(route('admin.products.index'));

        $store = Store::where('slug', 'nimstore')->firstOrFail();
        auth()->logout();

        return [$store, $store->products()->first()->variants()->first()];
    }

    private function checkout(Store $store, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        $pickup = $store->deliveryMethods()->where('type', 'pickup')->first();

        return $this->post("/{$store->slug}/commande", $overrides + [
            'name' => 'Mamadou Diallo',
            'phone' => '621 00 00 01',
            'delivery_method_id' => $pickup->id,
            'payment_method_id' => $store->cashOnDelivery()->id,
        ]);
    }

    public function test_full_loop_from_signup_to_whatsapp_order(): void
    {
        [$store, $variant] = $this->merchantWithProduct(150000);

        $this->assertSame('224620112233', $store->whatsapp_number);
        $this->get('/nimstore?src=facebook&c=promo-tabaski')->assertOk()->assertSee('Baskets blanches')->assertSee('150 000 GNF');
        $this->post('/nimstore/panier', ['variant_id' => $variant->id, 'quantity' => 2])->assertRedirect();
        $this->get('/nimstore/panier')->assertOk()->assertSee('300 000 GNF');
        $this->get('/nimstore/commande')->assertOk()->assertSee('Paiement à la livraison');

        $response = $this->checkout($store);
        $order = Order::firstOrFail();
        $response->assertRedirect(route('invoice.show', $order));

        $this->assertSame(1001, $order->number);
        $this->assertSame(300000, $order->total);
        $this->assertSame('facebook', $order->source);
        $this->assertSame('promo-tabaski', $order->campaign);
        $this->assertSame('224621000001', $order->customer_phone);
        $this->assertSame(PaymentMethod::CASH_ON_DELIVERY, $order->payment_method_type);

        $this->get(route('invoice.show', $order))->assertOk()->assertSee('Commande #1001')->assertSee('en espèces');

        $redirect = $this->get(route('invoice.whatsapp', $order));
        $target = $redirect->headers->get('Location');
        $this->assertStringStartsWith('https://wa.me/224620112233?text=', $target);
        $message = rawurldecode(substr($target, strpos($target, 'text=') + 5));
        $this->assertStringContainsString('#1001', $message);
        $this->assertStringContainsString('2 × Baskets blanches — 300 000 GNF', $message);
        $this->assertStringContainsString('Paiement : à la livraison (300 000 GNF à préparer)', $message);
        $this->assertNotNull($order->fresh()->whatsapp_sent_at);

        // Le panier est vidé après la commande.
        $this->get('/nimstore/panier')->assertSee('Votre panier est vide');

        // Le commerçant voit la commande.
        $this->actingAs($store->user)->get('/admin/commandes')->assertOk()->assertSee('#1001')->assertSee('via Facebook');
    }

    public function test_merchant_can_block_cash_on_delivery(): void
    {
        [$store, $variant] = $this->merchantWithProduct();
        $merchant = $store->user;

        // Impossible de tout couper : il faut un autre moyen actif d'abord.
        $this->actingAs($merchant)->put('/admin/paiement/livraison', ['is_active' => '0'])->assertSessionHasErrors('is_active');
        $this->assertTrue($store->cashOnDelivery()->is_active);

        $this->actingAs($merchant)->put('/admin/paiement/orange_money', ['is_active' => '1', 'account_number' => '622 00 00 00', 'account_name' => 'Nimaga'])
            ->assertSessionHasNoErrors();
        $this->actingAs($merchant)->put('/admin/paiement/livraison', ['is_active' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($store->cashOnDelivery()->is_active);
        auth()->logout();

        $this->post('/nimstore/panier', ['variant_id' => $variant->id]);
        $this->get('/nimstore/commande')->assertDontSee('En espèces, à la réception')->assertSee('Orange Money');

        // Même en forçant le formulaire, le serveur refuse.
        $this->checkout($store)->assertSessionHasErrors('payment_method_id');
        $this->assertSame(0, Order::count());

        $om = $store->paymentMethods()->where('type', PaymentMethod::ORANGE_MONEY)->first();
        $this->checkout($store, ['payment_method_id' => $om->id])->assertSessionHasNoErrors();
        $order = Order::firstOrFail();
        $this->get(route('invoice.show', $order))->assertSee('+224 622 00 00 00')->assertSee('Nom affiché : Nimaga');
    }

    public function test_cash_on_delivery_cap(): void
    {
        [$store, $variant] = $this->merchantWithProduct(400000);

        $this->actingAs($store->user)->put('/admin/paiement/livraison', ['is_active' => '1', 'limit' => '1', 'max_order_total' => '500 000'])
            ->assertSessionHasNoErrors();
        $this->assertSame(500000, $store->cashOnDelivery()->max_order_total);
        auth()->logout();

        $this->post('/nimstore/panier', ['variant_id' => $variant->id, 'quantity' => 2]); // 800 000 GNF
        $this->checkout($store)->assertSessionHasErrors(['payment_method_id' => 'Paiement à la livraison possible jusqu\'à 500 000 GNF.']);

        $this->post('/nimstore/panier', []); // sans effet
        $this->patch('/nimstore/panier', ['variant_id' => $variant->id, 'quantity' => 1]); // 400 000 GNF
        $this->checkout($store)->assertSessionHasNoErrors();
        $this->assertSame(1, Order::count());
    }

    public function test_cash_on_delivery_per_delivery_method_and_blocked_customer(): void
    {
        [$store, $variant] = $this->merchantWithProduct();
        $delivery = $store->deliveryMethods()->where('type', 'delivery')->first();
        $delivery->update(['allows_cash_on_delivery' => false]);

        $this->post('/nimstore/panier', ['variant_id' => $variant->id]);
        $this->checkout($store, ['delivery_method_id' => $delivery->id, 'address' => 'Ratoma, près du marché'])
            ->assertSessionHasErrors('payment_method_id');

        $this->checkout($store)->assertSessionHasNoErrors(); // retrait : permis
        $store->customers()->where('phone', '224621000001')->update(['cash_on_delivery_blocked' => true]);

        $this->post('/nimstore/panier', ['variant_id' => $variant->id]);
        $this->checkout($store)->assertSessionHasErrors(['payment_method_id' => 'Paiement avant livraison requis pour cette commande.']);
        $this->assertSame(1, Order::count());
    }

    public function test_stock_is_never_oversold_and_cancel_restocks(): void
    {
        [$store, $variant] = $this->merchantWithProduct(100000, 2);

        // Ajout au-delà du stock refusé.
        $this->post('/nimstore/panier', ['variant_id' => $variant->id, 'quantity' => 3])->assertSessionHasErrors('quantity');

        $this->post('/nimstore/panier', ['variant_id' => $variant->id, 'quantity' => 2]);
        // Quelqu'un achète entre-temps une unité.
        Variant::whereKey($variant->id)->update(['stock' => 1]);
        $this->checkout($store)->assertSessionHasErrors(['cart' => '« Baskets blanches » : plus que 1 en stock.']);
        $this->assertSame(0, Order::count());
        $this->assertSame(1, $variant->fresh()->stock);

        $this->patch('/nimstore/panier', ['variant_id' => $variant->id, 'quantity' => 1]);
        $this->checkout($store)->assertSessionHasNoErrors();
        $this->assertSame(0, $variant->fresh()->stock);
        $this->get('/nimstore')->assertSee('Épuisé');

        $order = Order::firstOrFail();
        $this->actingAs($store->user)->post("/admin/commandes/{$order->id}/annuler")->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(1, $variant->fresh()->stock);

        // Annuler deux fois ne remet pas le stock deux fois.
        $this->actingAs($store->user)->post("/admin/commandes/{$order->id}/annuler");
        $this->assertSame(1, $variant->fresh()->stock);
    }

    public function test_merchants_never_see_each_other_orders(): void
    {
        [$store, $variant] = $this->merchantWithProduct();
        $this->post('/nimstore/panier', ['variant_id' => $variant->id]);
        $this->checkout($store);
        $order = Order::firstOrFail();

        $other = User::factory()->create();
        $otherStore = new Store(['name' => 'Autre', 'slug' => 'autre', 'whatsapp_number' => '224622222222']);
        $otherStore->user()->associate($other)->save();

        $this->actingAs($other)->get("/admin/commandes/{$order->id}")->assertNotFound();
        $this->actingAs($other)->post("/admin/commandes/{$order->id}/annuler")->assertNotFound();
        $this->actingAs($other)->get('/admin/commandes')->assertDontSee('Mamadou');

        // Ajouter au panier un produit d'une autre boutique : refusé.
        $this->post('/autre/panier', ['variant_id' => $variant->id])->assertNotFound();
    }

    public function test_reserved_and_taken_store_links_are_refused(): void
    {
        $this->actingAs(User::factory()->create());
        $base = ['name' => 'X', 'whatsapp_number' => '620000000', 'city' => 'Conakry'];

        $this->post('/admin/boutique/creer', $base + ['slug' => 'admin'])->assertSessionHasErrors('slug');
        $this->post('/admin/boutique/creer', $base + ['slug' => 'Ma Boutique Été'])->assertSessionHasNoErrors();
        $this->assertSame('ma-boutique-ete', Store::firstOrFail()->slug);

        $this->actingAs(User::factory()->create());
        $this->post('/admin/boutique/creer', $base + ['slug' => 'ma-boutique-ete'])->assertSessionHasErrors('slug');
    }

    public function test_admin_requires_login_and_a_store(): void
    {
        $this->get('/admin/commandes')->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get('/admin/commandes')->assertRedirect(route('admin.store.create'));
    }

    public function test_many_cart_additions_do_not_block_checkout(): void
    {
        // Régression : les limites anti-abus partageaient un compteur par IP pour toutes les routes.
        [$store, $variant] = $this->merchantWithProduct();
        for ($i = 0; $i < 12; $i++) {
            $this->post('/nimstore/panier', ['variant_id' => $variant->id])->assertRedirect();
        }
        $this->checkout($store)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(12, Order::firstOrFail()->items()->first()->quantity);
    }

    public function test_login_with_any_phone_format(): void
    {
        User::factory()->create(['phone' => '224620000000']);
        $this->post('/connexion', ['phone' => '+224 620 00 00 00', 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticated();
    }
}
