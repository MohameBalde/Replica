<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Suppléments payants d'un produit (ex. flocage, emballage cadeau), avec un texte
// demandé au client si besoin. Les suppléments choisis sont copiés sur la ligne de
// commande : le prix unitaire de la ligne les inclut déjà.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('addons')->nullable()->after('track_stock');  // [{key, name, price, text}]
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->json('options')->nullable()->after('variant_name'); // [{name, price, text}]
        });
    }

    public function down(): void
    {
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn('options'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('addons'));
    }
};
