<?php 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['borrador','enviada','parcial','recibida','cancelada'])->default('borrador');
            $table->date('ordered_date')->nullable();
            $table->date('expected_date')->nullable();
            $table->date('received_date')->nullable();
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_ordered', 12, 4);
            $table->decimal('quantity_received', 12, 4)->default(0);
            $table->decimal('unit_cost', 12, 2);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down():void
    {
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_order_items');
    }
};


?>