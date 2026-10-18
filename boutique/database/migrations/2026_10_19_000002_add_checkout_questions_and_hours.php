<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Réglages « Commande » : questions posées au client (ex. point de repère) et
// horaires d'ouverture. Les réponses sont copiées sur la commande.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->json('checkout_questions')->nullable()->after('welcome_message');  // [{label, required}]
            $table->json('opening_hours')->nullable()->after('checkout_questions');    // {1..7: {open, close} | null}
            $table->boolean('closed_outside_hours')->default(false)->after('opening_hours');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->json('answers')->nullable()->after('note');                         // [{q, a}]
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropColumn('answers'));
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn(['checkout_questions', 'opening_hours', 'closed_outside_hours']));
    }
};
