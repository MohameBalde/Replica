<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le commerçant qui regarde sa propre vitrine (app installée sur ordinateur : pas de bouton « retour »)
 * a un lien vers son espace. Les clients et les autres commerçants ne le voient pas.
 */
class StoreOwnerBarTest extends TestCase
{
    use RefreshDatabase;

    private const BAR = 'Retour à mon espace';

    private Store $store;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->post('/inscription', [
            'name' => 'Aïssatou', 'phone' => '620 11 22 33',
            'password' => 'motdepasse', 'password_confirmation' => 'motdepasse',
        ]);
        $this->post('/admin/boutique/creer', ['name' => 'NimStore', 'slug' => 'nimstore', 'whatsapp_number' => '620 11 22 33', 'city' => 'Conakry']);
        $this->post('/admin/produits', ['name' => 'Baskets', 'price' => '150000'])->assertSessionHasNoErrors();
        $this->store = Store::where('slug', 'nimstore')->firstOrFail();
        $this->product = $this->store->products()->firstOrFail();
    }

    /** @return list<string> */
    private function storefrontPages(): array
    {
        return ['/nimstore', '/nimstore/p/'.$this->product->slug, '/nimstore/panier'];
    }

    public function test_owner_sees_the_way_back_on_storefront_pages(): void
    {
        foreach ($this->storefrontPages() as $url) {
            $this->get($url)->assertOk()
                ->assertSeeInOrder(['href="'.route('admin.home').'"', self::BAR], false);
        }
    }

    public function test_customers_do_not_see_it(): void
    {
        auth()->logout();

        foreach ($this->storefrontPages() as $url) {
            $this->get($url)->assertOk()->assertDontSee(self::BAR);
        }
    }

    public function test_other_merchants_do_not_see_it(): void
    {
        auth()->logout();
        $this->post('/inscription', [
            'name' => 'Souleymane', 'phone' => '621 99 88 77',
            'password' => 'motdepasse', 'password_confirmation' => 'motdepasse',
        ]);
        $this->post('/admin/boutique/creer', ['name' => 'Nimaga', 'slug' => 'nimaga', 'whatsapp_number' => '621 99 88 77', 'city' => 'Conakry']);

        $this->get('/nimstore')->assertOk()->assertDontSee(self::BAR);
        $this->get('/nimaga')->assertOk()->assertSee(self::BAR);
    }
}
