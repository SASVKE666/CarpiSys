<?php 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('idle_time_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('cause', [
                'busqueda_material',
                'calculo_manual',
                'retraso_compra',
                'falta_herramienta',
                'retrabajo',
                'otro'
            ]);
            $table->decimal('duration_min', 7, 2);
            $table->string('worker_name', 120)->nullable();
            $table->decimal('economic_cost', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idle_time_records');
    }
};