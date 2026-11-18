<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up():void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('client_name', 150);
            $table->string('client_contact', 100)->nullable();
            $table->text('description');
            $table->enum('status', ['pendiente','en_proceso','pausada','completada','cancelada'])
                ->default('pendiente');
            $table->date('start_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('completed_date')->nullable();
            $table->decimal('materials_cost', 14, 2)->default(0);
            $table->decimal('labor_cost', 14, 2)->default(0);
            $table->decimal('overhead_cost', 14, 2)->default(0);
            $table->decimal('quoted_price', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('status');
        });
    }

    public function down():void
    {
        Schema::dropIfExists('work_orders');
    }
};
