<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// « partially_paid » fait 14 caractères : la colonne de 12 faisait échouer
// « Payée en partie » sur MySQL. SQLite (tests) ne limite pas la longueur.
return new class extends Migration
{
    public function up(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('orders', fn (Blueprint $table) => $table->string('payment_status', 20)->default('unpaid')->change());
        }
    }

    public function down(): void
    {
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            Schema::table('orders', fn (Blueprint $table) => $table->string('payment_status', 12)->default('unpaid')->change());
        }
    }
};
