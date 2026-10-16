<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Abonnement prépayé : aucune carte, aucun prélèvement. Le commerçant paie par
// Orange Money / MTN, l'équipe enregistre le paiement, la date de fin avance.
// À l'échéance, la boutique repasse en Gratuit sans rien perdre.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('plan', 12)->default('free')->after('next_order_number');
            $table->timestamp('plan_expires_at')->nullable()->after('plan');
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('plan', 12);
            $table->unsignedSmallInteger('months');
            $table->unsignedBigInteger('amount'); // GNF
            $table->string('method', 20);          // orange_money | mtn_momo | cash
            $table->string('reference', 60)->nullable();
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->timestamps();
            $table->index(['store_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn(['plan', 'plan_expires_at']));
    }
};
