<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->enum('movement_type', ['entrada','salida','ajuste','merma','traspaso','devolucion']);
            $table->decimal('quantity', 12, 4);
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('total_cost', 14, 2)->virtualAs('quantity * unit_cost');
            $table->string('reference_type', 60)->nullable(); // 'work_order','purchase_order','manual'
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['item_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down():void
    {
        Schema::dropIfExists('inventory_movements');
    }
};