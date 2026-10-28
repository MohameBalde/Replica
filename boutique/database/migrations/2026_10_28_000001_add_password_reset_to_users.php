<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Mot de passe oublié : l'équipe crée un lien (valable 1 heure, une seule fois) et l'envoie
// sur le WhatsApp du numéro du compte. Seule l'empreinte du lien est enregistrée.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password_reset_hash', 64)->nullable()->unique()->after('password');
            $table->timestamp('password_reset_expires_at')->nullable()->after('password_reset_hash');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['password_reset_hash']);
            $table->dropColumn(['password_reset_hash', 'password_reset_expires_at']);
        });
    }
};
