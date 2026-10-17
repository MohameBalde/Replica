<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Traduction de replica/schema.sql. Argent en entiers GNF, heures en UTC.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 60)->unique();
            $table->string('whatsapp_number', 20);
            $table->string('city', 60)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('logo_path')->nullable();
            $table->char('currency', 3)->default('GNF');
            $table->boolean('is_open')->default(true);
            $table->text('welcome_message')->nullable();
            $table->unsignedInteger('next_order_number')->default(1001);
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['store_id', 'position']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('slug', 160);
            $table->text('description')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->boolean('track_stock')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['store_id', 'slug']);
            $table->index(['store_id', 'is_visible', 'position']);
        });

        // Tout produit a au moins une variante ("Standard") : prix et stock vivent ici.
        Schema::create('variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80)->default('Standard');
            $table->unsignedBigInteger('price');
            $table->integer('stock')->nullable();
            $table->string('sku', 60)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['product_id', 'position']);
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('thumb_path');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['product_id', 'position']);
        });

        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('phone', 20);
            $table->string('address')->nullable();
            $table->boolean('cash_on_delivery_blocked')->default(false);
            $table->timestamps();
            $table->unique(['store_id', 'phone']);
        });

        Schema::create('delivery_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('type', 10); // pickup | delivery
            $table->unsignedBigInteger('fee')->default(0);
            $table->unsignedBigInteger('min_order')->default(0);
            $table->boolean('allows_cash_on_delivery')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->index(['store_id', 'is_active', 'position']);
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // cash_on_delivery | orange_money | mtn_momo | bank_transfer
            $table->string('account_number', 30)->nullable();
            $table->string('account_name', 120)->nullable();
            $table->string('instructions', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('max_order_total')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['store_id', 'type']);
            $table->index(['store_id', 'is_active', 'position']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('number');
            $table->char('public_token', 26)->unique();
            $table->string('status', 12)->default('pending');
            $table->string('payment_status', 12)->default('unpaid');
            $table->string('fulfillment_status', 16)->default('unfulfilled');
            $table->string('customer_name', 120);
            $table->string('customer_phone', 20);
            $table->string('address')->nullable();
            $table->string('note', 500)->nullable();
            $table->foreignId('delivery_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('delivery_method_name', 80);
            $table->unsignedBigInteger('delivery_fee')->default(0);
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method_type', 20);
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('total');
            $table->char('currency', 3)->default('GNF');
            $table->string('source', 20)->default('direct');
            $table->string('campaign', 60)->nullable();
            $table->string('internal_note', 500)->nullable();
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['store_id', 'number']);
            $table->index(['store_id', 'status', 'created_at']);
            $table->index(['store_id', 'payment_status', 'created_at']);
            $table->index(['store_id', 'source', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name', 150);
            $table->string('variant_name', 80);
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total');
            $table->timestamps();
        });

        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('reference', 60)->nullable();
            $table->timestamps();
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('delta');
            $table->string('reason', 12); // sale | cancel | restock | adjust
            $table->timestamps();
            $table->index(['variant_id', 'created_at']);
            $table->index(['store_id', 'created_at']);
        });

        Schema::create('daily_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->string('source', 20);
            $table->unsignedInteger('visits')->default(0);
            $table->timestamps();
            $table->unique(['store_id', 'day', 'source']);
        });

        // Garde-fous en base (MySQL 8.0.16+). SQLite (tests) s'appuie sur la validation de l'app.
        if (DB::getDriverName() === 'mysql') {
            $checks = [
                "ALTER TABLE stores ADD CONSTRAINT stores_slug_format CHECK (slug REGEXP '^[a-z0-9][a-z0-9-]{1,58}[a-z0-9]$')",
                'ALTER TABLE variants ADD CONSTRAINT variants_stock_not_negative CHECK (stock IS NULL OR stock >= 0)',
                "ALTER TABLE delivery_methods ADD CONSTRAINT delivery_methods_type CHECK (type IN ('pickup','delivery'))",
                "ALTER TABLE payment_methods ADD CONSTRAINT payment_methods_type CHECK (type IN ('cash_on_delivery','orange_money','mtn_momo','bank_transfer'))",
                "ALTER TABLE orders ADD CONSTRAINT orders_status CHECK (status IN ('pending','confirmed','completed','cancelled'))",
                "ALTER TABLE orders ADD CONSTRAINT orders_payment_status CHECK (payment_status IN ('unpaid','confirming','partially_paid','paid','refunded'))",
                "ALTER TABLE orders ADD CONSTRAINT orders_fulfillment_status CHECK (fulfillment_status IN ('unfulfilled','ready','out_for_delivery','fulfilled'))",
                'ALTER TABLE orders ADD CONSTRAINT orders_total CHECK (total = subtotal + delivery_fee)',
                'ALTER TABLE order_items ADD CONSTRAINT order_items_quantity CHECK (quantity > 0)',
                'ALTER TABLE order_items ADD CONSTRAINT order_items_line_total CHECK (line_total = unit_price * quantity)',
                "ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_reason CHECK (reason IN ('sale','cancel','restock','adjust'))",
            ];
            foreach ($checks as $sql) {
                DB::statement($sql);
            }
        }
    }

    public function down(): void
    {
        foreach (['daily_visits', 'stock_movements', 'payment_proofs', 'order_items', 'orders', 'payment_methods',
            'delivery_methods', 'customers', 'product_images', 'variants', 'products', 'categories', 'stores'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
