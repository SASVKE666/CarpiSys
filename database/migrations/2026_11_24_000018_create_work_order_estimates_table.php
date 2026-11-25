<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Material que el administrador calcula que necesita una orden (el "plan"). Es la base para
        // comparar, al completar la orden, contra lo que realmente se registró como salida.
        Schema::create('work_order_estimates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 12, 4);
            $table->timestamps();

            $table->unique(['work_order_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_estimates');
    }
};
