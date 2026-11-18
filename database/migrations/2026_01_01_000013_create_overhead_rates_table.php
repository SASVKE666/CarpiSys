<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
       Schema::create('overhead_rates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->enum('rate_type', [
                'porcentaje_mano_obra',
                'porcentaje_materiales',
                'por_hora_maquina',
                'monto_fijo_mensual'
            ]);
            $table->decimal('value', 10, 4);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('overhead_rates');
    }
};