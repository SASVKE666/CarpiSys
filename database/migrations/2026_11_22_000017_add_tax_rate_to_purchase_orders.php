<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            // % de IVA con el que se calculó "tax". Se guarda para poder recalcular los totales
            // si el administrador corrige un precio al recibir la mercancía.
            $table->decimal('tax_rate', 5, 2)->default(16)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('tax_rate');
        });
    }
};
