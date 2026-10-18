<?php

namespace Tests\Feature;

use App\Models\Store;
use App\Models\User;
use App\Support\Plans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->merchant = User::factory()->create();
        $this->store = new Store(['name' => 'NimStore', 'slug' => 'nimstore', 'whatsapp_number' => '224620000000']);
        $this->store->user()->associate($this->merchant)->save();
        $this->actingAs($this->merchant);
    }

    public function test_free_plan_is_limited_to_15_products_without_losing_any(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            $this->post('/admin/produits', ['name' => "Produit $i", 'price' => '1000'])->assertSessionHasNoErrors();
        }
        $this->get('/admin/produits/creer')->assertRedirect(route('admin.subscription'));
        $this->post('/admin/produits', ['name' => 'Produit 16', 'price' => '1000'])->assertRedirect(route('admin.subscription'));
        $this->assertSame(15, $this->store->products()->count());

        // Une fois Commerçant payé, on peut en ajouter.
        $this->artisan('wanllan:abonnement', ['boutique' => 'nimstore', 'formule' => 'merchant', 'mois' => 1, '--ref' => 'MP1'])
            ->assertSuccessful();
        $this->post('/admin/produits', ['name' => 'Produit 16', 'price' => '1000'])->assertRedirect(route('admin.products.index'));
        $this->assertSame(16, $this->store->products()->count());
    }

    public function test_prepaid_periods_stack_and_expire_back_to_free_without_any_charge(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 1)->startOfDay());
        $this->store->recordSubscription(Plans::MERCHANT, 1, 50000, 'orange_money');
        $this->store->recordSubscription(Plans::MERCHANT, 1, 50000, 'orange_money'); // payé en avance : s'ajoute à la suite
        $this->store->refresh();
        $this->assertSame('2027-01-01', $this->store->plan_expires_at->toDateString());
        $this->assertSame(Plans::MERCHANT, $this->store->activePlan());
        $this->assertSame(2, $this->store->subscriptionPayments()->count());

        // Rappel visible 3 jours avant la fin.
        $this->travelTo(now()->setDate(2026, 12, 30));
        $this->get('/admin/commandes')->assertSee('se termine dans 2 jours');

        // Après la date : retour en Découverte, rien de supprimé, rien de prélevé.
        $this->travelTo(now()->setDate(2027, 1, 2));
        $this->assertSame(Plans::FREE, $this->store->fresh()->activePlan());
        $this->assertSame(2, $this->store->subscriptionPayments()->count());
        $this->get('/admin/abonnement')->assertSee('Rien n\'est prélevé automatiquement', false)->assertSee('Découverte');
    }

    public function test_pro_removes_the_badge_and_annual_price_is_ten_months(): void
    {
        $this->get('/nimstore')->assertSee('Boutique créée avec');
        $this->artisan('wanllan:abonnement', ['boutique' => 'nimstore', 'formule' => 'pro', 'mois' => 12])->assertSuccessful();
        $this->assertSame(Plans::yearly(Plans::PRO), $this->store->subscriptionPayments()->first()->amount);
        $this->assertSame(10 * config('wanllan.prices.pro'), Plans::yearly(Plans::PRO));
        $this->get('/nimstore')->assertDontSee('Boutique créée avec');
    }

    public function test_unknown_store_or_plan_is_refused(): void
    {
        $this->artisan('wanllan:abonnement', ['boutique' => 'inconnue', 'formule' => 'pro'])->assertFailed();
        $this->artisan('wanllan:abonnement', ['boutique' => 'nimstore', 'formule' => 'gold'])->assertFailed();
    }

    public function test_landing_page_shows_real_prices_and_no_fake_proof(): void
    {
        auth()->logout();
        $this->get('/')->assertOk()
            ->assertSee('Votre boutique sur WhatsApp, en 2 minutes.')
            ->assertSee(gnf(config('wanllan.prices.merchant')))
            ->assertSee('Rien n\'est prélevé automatiquement')
            ->assertDontSee('Le plus choisi');
        $this->actingAs($this->merchant)->get('/')->assertRedirect(route('admin.home'));
    }

    public function test_public_pages_never_name_the_original_app(): void
    {
        // Les noms interdits viennent de replica/brand.json (hors de l'app, présent dans le dépôt).
        $brand = base_path('../replica/brand.json');
        if (! is_file($brand)) {
            $this->markTestSkipped('replica/brand.json absent');
        }
        $avoid = json_decode((string) file_get_contents($brand), true)['avoid'];

        auth()->logout();
        foreach (['/', '/inscription', '/conditions', '/confidentialite', '/'.$this->store->slug] as $page) {
            $html = mb_strtolower($this->get($page)->assertOk()->getContent());
            foreach ($avoid as $name) {
                $this->assertStringNotContainsString(mb_strtolower($name), $html, "« {$name} » apparaît sur {$page}");
            }
        }
    }
}
