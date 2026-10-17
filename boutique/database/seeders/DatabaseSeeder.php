<?php

namespace Database\Seeders;

use App\Http\Controllers\Admin\StoreSetupController;
use App\Models\DeliveryMethod;
use App\Models\PaymentMethod;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Boutique de démonstration : php artisan migrate:fresh --seed
 * Connexion : 620 00 00 00 / password
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create(['name' => 'Mohamed Baldé', 'phone' => '224620000000']);

        $store = new Store([
            'name' => 'NimStore',
            'slug' => 'nimstore',
            'whatsapp_number' => '224620000000',
            'city' => 'Conakry',
            'description' => 'Chaussures, survêtements et parfums',
            'welcome_message' => StoreSetupController::defaultWelcomeMessage('NimStore', 'nimstore', 'Conakry'),
        ]);
        $store->user()->associate($user);
        $store->save();

        $store->deliveryMethods()->createMany([
            ['name' => 'Livraison Ratoma', 'type' => DeliveryMethod::DELIVERY, 'fee' => 25000, 'position' => 0],
            ['name' => 'Livraison Kaloum', 'type' => DeliveryMethod::DELIVERY, 'fee' => 30000, 'position' => 1],
            ['name' => 'Livraison Kankan', 'type' => DeliveryMethod::DELIVERY, 'fee' => 100000, 'allows_cash_on_delivery' => false, 'position' => 2],
            ['name' => 'Retrait en boutique (Madina)', 'type' => DeliveryMethod::PICKUP, 'fee' => 0, 'position' => 3],
        ]);

        $store->paymentMethods()->createMany([
            ['type' => PaymentMethod::CASH_ON_DELIVERY, 'is_active' => true, 'max_order_total' => 1000000, 'position' => 0],
            ['type' => PaymentMethod::ORANGE_MONEY, 'is_active' => true, 'account_number' => '224620000000', 'account_name' => 'Nimaga S.', 'position' => 1],
            ['type' => PaymentMethod::MTN_MOMO, 'is_active' => false, 'position' => 2],
        ]);

        $catalog = [
            'Chaussures' => [
                ['Baskets blanches classiques', 350000, 12],
                ['Sandales cuir homme', 120000, 0],
                ['Mocassins daim', 280000, 5],
            ],
            'Survêtements' => [
                ['Survêtement Real Madrid 2026', 275000, 3],
                ['Survêtement Syli National', 250000, 8],
            ],
            'Parfums' => [
                ['Parfum oud 100 ml', 180000, null],
            ],
        ];

        $position = 0;
        foreach ($catalog as $categoryName => $products) {
            $category = $store->categories()->create(['name' => $categoryName, 'position' => $position]);
            foreach ($products as [$name, $price, $stock]) {
                $product = $store->products()->create([
                    'category_id' => $category->id,
                    'name' => $name,
                    'slug' => \Illuminate\Support\Str::slug($name),
                    'description' => 'Article de démonstration.',
                    'track_stock' => $stock !== null,
                    'position' => $position++,
                ]);
                if ($name === 'Survêtement Real Madrid 2026') {
                    // Démonstration des tailles : prix et stock par taille.
                    foreach ([['M', 275000, 2], ['L', 275000, 1], ['XL', 290000, 0]] as $i => [$size, $p, $s]) {
                        $product->variants()->create(['name' => $size, 'price' => $p, 'stock' => $s, 'position' => $i]);
                    }
                } else {
                    $product->variants()->create(['price' => $price, 'stock' => $stock]);
                }
            }
        }
    }
}
