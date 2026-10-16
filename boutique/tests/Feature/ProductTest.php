<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private User $merchant;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->merchant = User::factory()->create();
        $this->store = new Store(['name' => 'NimStore', 'slug' => 'nimstore', 'whatsapp_number' => '224620000000']);
        $this->store->user()->associate($this->merchant)->save();
    }

    public function test_photos_are_compressed_to_webp_with_thumbnail(): void
    {
        $photo = UploadedFile::fake()->image('basket.jpg', 3000, 2000)->size(4000);

        $this->actingAs($this->merchant)->post('/admin/produits', [
            'name' => 'Baskets', 'price' => '350 000', 'photos' => [$photo],
        ])->assertSessionHasNoErrors();

        $image = Product::firstOrFail()->images()->firstOrFail();
        Storage::disk('public')->assertExists([$image->path, $image->thumb_path]);
        $this->assertStringEndsWith('.webp', $image->path);

        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($image->path));
        $this->assertSame([1080, 720], [$w, $h]);
        [$tw, $th] = getimagesizefromstring(Storage::disk('public')->get($image->thumb_path));
        $this->assertSame([400, 400], [$tw, $th]);

        $this->get('/nimstore')->assertSee($image->thumb_path);
    }

    public function test_non_image_and_too_many_photos_are_refused(): void
    {
        $this->actingAs($this->merchant)->post('/admin/produits', [
            'name' => 'X', 'price' => '1000', 'photos' => [UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')],
        ])->assertSessionHasErrors('photos.0');

        $six = array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg", 50, 50), range(1, 6));
        $this->actingAs($this->merchant)->post('/admin/produits', ['name' => 'X', 'price' => '1000', 'photos' => $six])
            ->assertSessionHasErrors('photos');
        $this->assertSame(0, Product::count());
    }

    public function test_removing_a_photo_deletes_the_files(): void
    {
        $this->actingAs($this->merchant)->post('/admin/produits', [
            'name' => 'Baskets', 'price' => '1000', 'photos' => [UploadedFile::fake()->image('a.jpg', 100, 100)],
        ]);
        $product = Product::firstOrFail();
        $image = $product->images()->firstOrFail();

        $this->actingAs($this->merchant)->put("/admin/produits/{$product->id}", [
            'name' => 'Baskets', 'price' => '1000', 'is_visible' => '1', 'remove_images' => [$image->id],
        ])->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing([$image->path, $image->thumb_path]);
        $this->assertSame(0, $product->images()->count());
    }

    public function test_variants_with_their_own_price_and_stock(): void
    {
        $this->actingAs($this->merchant)->post('/admin/produits', [
            'name' => 'Survêtement', 'price' => '250 000',
            'variants' => [
                ['name' => 'M', 'price' => '', 'stock' => '3'],
                ['name' => 'L', 'price' => '260 000', 'stock' => ''],
                ['name' => '', 'price' => '', 'stock' => ''], // ligne vide ignorée
            ],
        ])->assertSessionHasNoErrors();

        $product = Product::with('variants')->firstOrFail();
        $this->assertTrue($product->track_stock);
        $this->assertSame(['M', 'L'], $product->variants->pluck('name')->all());
        $this->assertSame([250000, 260000], $product->variants->pluck('price')->all());
        $this->assertSame([3, 0], $product->variants->pluck('stock')->all()); // stock vide = 0 quand on suit le stock

        // Côté client : L est épuisé, M sélectionné, prix de M affiché.
        $this->get("/nimstore/p/{$product->slug}")->assertOk()
            ->assertSee('Choisissez')->assertSee('250 000 GNF')->assertSee('(épuisé)', false);

        // Mise à jour : on renomme M, on supprime L, on ajoute XL.
        [$m, $l] = $product->variants;
        $this->actingAs($this->merchant)->put("/admin/produits/{$product->id}", [
            'name' => 'Survêtement', 'price' => '250 000', 'is_visible' => '1',
            'variants' => [
                ['id' => $m->id, 'name' => 'Taille M', 'price' => '', 'stock' => '5'],
                ['id' => $l->id, 'name' => '', 'price' => '', 'stock' => ''],
                ['id' => '', 'name' => 'XL', 'price' => '', 'stock' => '1'],
            ],
        ])->assertSessionHasNoErrors();

        $variants = $product->variants()->get();
        $this->assertSame(['Taille M', 'XL'], $variants->pluck('name')->all());
        $this->assertSame($m->id, $variants[0]->id);
        $this->assertSame(5, $variants[0]->stock);
        $this->assertDatabaseHas('stock_movements', ['variant_id' => $m->id, 'delta' => 2, 'reason' => 'adjust']);
    }

    public function test_duplicate_variant_names_are_refused(): void
    {
        $this->actingAs($this->merchant)->post('/admin/produits', [
            'name' => 'X', 'price' => '1000', 'variants' => [['name' => '42'], ['name' => '42']],
        ])->assertSessionHasErrors();
        $this->assertSame(0, Product::count());
    }

    public function test_cannot_edit_another_merchant_product(): void
    {
        $this->actingAs($this->merchant)->post('/admin/produits', ['name' => 'Baskets', 'price' => '1000']);
        $product = Product::firstOrFail();

        $other = User::factory()->create();
        (new Store(['name' => 'B', 'slug' => 'b-shop', 'whatsapp_number' => '224621111111']))->user()->associate($other)->save();

        $this->actingAs($other)->get("/admin/produits/{$product->id}")->assertNotFound();
        $this->actingAs($other)->put("/admin/produits/{$product->id}", ['name' => 'Volé', 'price' => '1'])->assertNotFound();
        $this->assertSame('Baskets', $product->fresh()->name);
    }
}
