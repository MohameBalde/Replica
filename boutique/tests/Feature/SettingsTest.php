<?php

namespace Tests\Feature;

use App\Models\DeliveryMethod;
use App\Models\Order;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->merchant = User::factory()->create();
        $this->actingAs($this->merchant)->post('/admin/boutique/creer', [
            'name' => 'NimStore', 'slug' => 'nimstore', 'whatsapp_number' => '620000000', 'city' => 'Conakry',
        ]);
        $this->store = Store::firstOrFail();
    }

    public function test_add_delivery_method_and_block_cash_on_delivery_for_it(): void
    {
        $this->get('/admin/livraison')->assertOk()->assertSee('Retrait en boutique')->assertSee('Kaloum');

        $this->post('/admin/livraison', [
            'name' => 'Livraison Kankan', 'type' => 'delivery', 'fee' => '100 000', 'min_order' => '200 000', 'is_active' => '1',
            // allows_cash_on_delivery absent = case décochée
        ])->assertSessionHasNoErrors();

        $kankan = DeliveryMethod::where('name', 'Livraison Kankan')->firstOrFail();
        $this->assertSame(100000, $kankan->fee);
        $this->assertSame(200000, $kankan->min_order);
        $this->assertFalse($kankan->allows_cash_on_delivery);
        $this->get('/admin/livraison')->assertSee('Payer avant');

        // Puis on le réautorise.
        $this->put("/admin/livraison/{$kankan->id}", [
            'name' => 'Livraison Kankan', 'type' => 'delivery', 'fee' => '100000', 'min_order' => '0',
            'allows_cash_on_delivery' => '1', 'is_active' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertTrue($kankan->fresh()->allows_cash_on_delivery);
    }

    public function test_cannot_disable_the_last_delivery_method(): void
    {
        $methods = $this->store->deliveryMethods()->get();
        $this->put("/admin/livraison/{$methods[1]->id}", ['name' => $methods[1]->name, 'type' => 'delivery', 'fee' => '0', 'min_order' => '0'])
            ->assertSessionHasNoErrors();
        $this->put("/admin/livraison/{$methods[0]->id}", ['name' => $methods[0]->name, 'type' => 'pickup', 'fee' => '0', 'min_order' => '0'])
            ->assertSessionHasErrors('delivery');
        $this->assertTrue($methods[0]->fresh()->is_active);
    }

    public function test_closing_the_store_blocks_orders(): void
    {
        $this->post('/admin/produits', ['name' => 'Baskets', 'price' => '1000']);
        $this->post('/admin/reglages/ouverture')->assertSessionHas('status');
        $this->assertFalse($this->store->fresh()->is_open);
        auth()->logout();

        $variant = $this->store->products()->first()->variants()->first();
        $this->get('/nimstore')->assertSee('Fermé');
        $this->post('/nimstore/panier', ['variant_id' => $variant->id]);
        $this->post('/nimstore/commande', [
            'name' => 'A', 'phone' => '621000001',
            'delivery_method_id' => $this->store->deliveryMethods()->first()->id,
            'payment_method_id' => $this->store->cashOnDelivery()->id,
        ])->assertSessionHasErrors('cart');
        $this->assertSame(0, Order::count());
    }

    public function test_store_settings_update_keeps_the_link(): void
    {
        $this->put('/admin/reglages', [
            'name' => 'NimStore Madina', 'description' => 'Chaussures', 'whatsapp_number' => '+224 621 11 11 11', 'city' => 'Kankan', 'is_open' => '1',
        ])->assertSessionHasNoErrors();

        $store = $this->store->fresh();
        $this->assertSame('NimStore Madina', $store->name);
        $this->assertSame('224621111111', $store->whatsapp_number);
        $this->assertSame('nimstore', $store->slug);
    }

    public function test_share_page_links_qr_and_source_stats(): void
    {
        $this->post('/admin/produits', ['name' => 'Baskets', 'price' => '150 000']);

        $this->get('/admin/partager')->assertOk()
            ->assertSee('/nimstore?src=facebook')->assertSee('/nimstore?src=tiktok')
            ->assertSee('/nimstore/p/baskets?src=facebook')
            ->assertSee('Merci de contacter NimStore');

        $qr = $this->get('/admin/partager/qr.svg');
        $qr->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<svg', $qr->getContent());

        $this->put('/admin/partager/message', ['welcome_message' => 'Salut ! Commandez ici'])->assertSessionHasNoErrors();
        $this->assertSame('Salut ! Commandez ici', $this->store->fresh()->welcome_message);

        // Une visite et une commande venues de TikTok apparaissent dans le tableau.
        auth()->logout();
        $variant = $this->store->products()->first()->variants()->first();
        $this->get('/nimstore?src=tiktok');
        $this->post('/nimstore/panier', ['variant_id' => $variant->id]);
        $this->post('/nimstore/commande', [
            'name' => 'A', 'phone' => '621000001',
            'delivery_method_id' => $this->store->deliveryMethods()->where('type', 'pickup')->first()->id,
            'payment_method_id' => $this->store->cashOnDelivery()->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($this->merchant)->get('/admin/partager')
            ->assertSee('Ce qui fait vendre')->assertSeeInOrder(['TikTok', '1', '1', '150 000 GNF']);
    }
}
