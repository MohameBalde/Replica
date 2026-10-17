<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use App\Services\ImageProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Ce que demande la mise en ligne : pages légales, suppression de compte, aperçus, proxys. */
class ProductionReadinessTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');

        $this->merchant = User::factory()->create();
        $this->actingAs($this->merchant)->post('/admin/boutique/creer', [
            'name' => 'NimStore', 'slug' => 'nimstore', 'whatsapp_number' => '620000000', 'city' => 'Conakry',
        ]);
        $this->store = Store::firstOrFail();
    }

    protected function tearDown(): void
    {
        TrustProxies::flushState();
        parent::tearDown();
    }

    private function addProductWithPhoto(): Product
    {
        $this->actingAs($this->merchant)->post('/admin/produits', [
            'name' => 'Survêtement', 'price' => '150000', 'stock' => '5',
            'photos' => [UploadedFile::fake()->image('survet.jpg', 1600, 1200)],
        ])->assertSessionHasNoErrors();

        return Product::with('images')->firstOrFail();
    }

    public function test_legal_pages_are_public_and_linked(): void
    {
        auth()->logout();

        $this->get('/confidentialite')->assertOk()->assertSee('Politique de confidentialité')->assertSee('Amazon Web Services');
        $this->get('/conditions')->assertOk()->assertSee('Conditions d\'utilisation', false)->assertSee('aucune commission', false);

        $this->get('/')->assertSee(route('legal.terms'))->assertSee(route('legal.privacy'));
        $this->get('/inscription')->assertSee(route('legal.terms'))->assertSee(route('legal.privacy'));
    }

    public function test_legal_paths_cannot_become_store_links(): void
    {
        $other = User::factory()->create();
        $this->actingAs($other)->post('/admin/boutique/creer', [
            'name' => 'Conditions', 'slug' => 'conditions', 'whatsapp_number' => '621000000', 'city' => 'Conakry',
        ])->assertSessionHasErrors('slug');
    }

    public function test_merchant_deletes_account_with_password_and_everything_goes(): void
    {
        $product = $this->addProductWithPhoto();
        $photo = $product->images->first();
        Storage::disk('local')->put('proofs/'.$this->store->id.'/capture.webp', 'x');
        $this->get(route('shop.product', [$this->store, $product]))->assertOk(); // crée l'aperçu JPEG
        Storage::disk('public')->assertExists(ImageProcessor::previewPath($photo->path));

        $this->actingAs($this->merchant)->delete('/admin/compte', ['password' => 'mauvais'])
            ->assertSessionHasErrors('password');
        $this->assertModelExists($this->merchant);

        $this->actingAs($this->merchant)->delete('/admin/compte', ['password' => 'password'])
            ->assertRedirect(route('home'));

        $this->assertGuest();
        $this->assertModelMissing($this->merchant);
        $this->assertSame(0, Store::count());
        $this->assertSame(0, Product::count());
        Storage::disk('public')->assertMissing($photo->path);
        Storage::disk('public')->assertMissing(ImageProcessor::previewPath($photo->path));
        Storage::disk('local')->assertMissing('proofs/'.$this->store->id.'/capture.webp');
        $this->get('/nimstore')->assertNotFound();
    }

    public function test_store_and_product_links_have_a_jpeg_preview_under_300_kb(): void
    {
        $product = $this->addProductWithPhoto();
        $preview = ImageProcessor::previewPath($product->images->first()->path);

        $this->get(route('shop.product', [$this->store, $product]))
            ->assertOk()
            ->assertSee('<meta property="og:image" content="'.asset('storage/'.$preview).'">', false)
            ->assertSee('<meta property="og:title" content="Survêtement · NimStore">', false)
            ->assertSee('150 000 GNF');
        $this->get('/nimstore')->assertSee(asset('storage/'.$preview));

        $bytes = Storage::disk('public')->get($preview);
        $size = getimagesizefromstring($bytes);
        $this->assertSame([1200, 630, IMAGETYPE_JPEG], [$size[0], $size[1], $size[2]]);
        $this->assertLessThan(300 * 1024, strlen($bytes));
    }

    public function test_pages_without_photo_use_the_wanllan_preview(): void
    {
        $this->get('/nimstore')->assertSee('<meta property="og:image" content="'.asset('og-wanllan.jpg').'">', false);
        $this->assertFileExists(public_path('og-wanllan.jpg'));
    }

    public function test_forwarded_ip_is_ignored_unless_a_proxy_is_configured(): void
    {
        Route::get('/_test/ip/check', fn () => request()->ip());

        // Par défaut, personne n'est proxy : un visiteur ne peut pas choisir son IP.
        $this->get('/_test/ip/check', ['X-Forwarded-For' => '1.2.3.4'])->assertSee('127.0.0.1');

        config(['trustedproxy.proxies' => '*']);
        $this->get('/_test/ip/check', ['X-Forwarded-For' => '1.2.3.4'])->assertSee('1.2.3.4');
    }
}
