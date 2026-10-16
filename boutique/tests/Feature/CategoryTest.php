<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_rename_reorder_and_delete_categories(): void
    {
        $merchant = User::factory()->create();
        $store = new Store(['name' => 'NimStore', 'slug' => 'nimstore', 'whatsapp_number' => '224620000000']);
        $store->user()->associate($merchant)->save();
        $this->actingAs($merchant);

        $this->post('/admin/categories', ['name' => 'Chaussures'])->assertSessionHasNoErrors();
        $this->post('/admin/categories', ['name' => 'Parfums'])->assertSessionHasNoErrors();
        $this->post('/admin/categories', ['name' => 'Parfums'])->assertSessionHasErrors('name');
        $this->post('/admin/produits', ['name' => 'Oud', 'price' => '1000', 'category' => 'Parfums']);

        [$shoes, $perfume] = $store->categories()->get();
        $this->post("/admin/categories/{$perfume->id}/deplacer", ['direction' => 'up']);
        $this->assertSame(['Parfums', 'Chaussures'], $store->categories()->pluck('name')->all());

        $this->put("/admin/categories/{$shoes->id}", ['name' => 'Baskets'])->assertSessionHasNoErrors();
        $this->get('/admin/categories')->assertOk()->assertSeeInOrder(['Parfums', 'Baskets']);

        $this->delete("/admin/categories/{$perfume->id}")->assertSessionHas('status');
        $product = Product::firstOrFail();
        $this->assertNull($product->category_id);
        $this->assertTrue($product->is_visible);

        // Isolation : un autre commerçant ne touche pas à ces catégories.
        $other = User::factory()->create();
        (new Store(['name' => 'B', 'slug' => 'b-shop', 'whatsapp_number' => '224621111111']))->user()->associate($other)->save();
        $this->actingAs($other)->delete("/admin/categories/{$shoes->id}")->assertNotFound();
    }
}
