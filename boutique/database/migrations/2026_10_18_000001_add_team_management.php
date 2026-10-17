<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Espace « Gestion » de l'équipe Wanllan : qui y a accès, les boutiques
// suspendues, et la trace de chaque action faite sur une boutique.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_team')->default(false)->after('password');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('plan_expires_at');
            $table->string('suspension_reason', 255)->nullable()->after('suspended_at');
        });

        Schema::create('team_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('store_id')->nullable()->constrained()->nullOnDelete();
            $table->string('store_name', 120); // reste lisible si la boutique est supprimée
            $table->string('action', 30);      // paiement | paiement_annule | suspension | reactivation
            $table->string('details', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['store_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_actions');
        Schema::table('stores', fn (Blueprint $table) => $table->dropColumn(['suspended_at', 'suspension_reason']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_team'));
    }
};
