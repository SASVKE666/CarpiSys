<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tarifa por hora de cada persona (la define el administrador). Con ella se costea
        // la mano de obra sin que el operario tenga que escribir ni ver su sueldo.
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('hourly_rate', 10, 2)->nullable()->after('is_active');
        });

        // Enlace entre el material usado en una orden y el movimiento de inventario que lo descontó,
        // para poder revertirlo con una devolución.
        Schema::table('work_order_materials', function (Blueprint $table) {
            $table->foreignId('movement_id')->nullable()->after('location_id')
                  ->constrained('inventory_movements')->nullOnDelete();
        });

        // Quién registró las horas.
        Schema::table('labor_records', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('work_order_id')
                  ->constrained()->nullOnDelete();
        });

        // Horas de máquina de la orden (base para las tasas CIF "por hora máquina").
        Schema::table('work_orders', function (Blueprint $table) {
            $table->decimal('machine_hours', 8, 2)->default(0)->after('completed_date');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', fn (Blueprint $t) => $t->dropColumn('machine_hours'));
        Schema::table('labor_records', function (Blueprint $t) {
            $t->dropConstrainedForeignId('user_id');
        });
        Schema::table('work_order_materials', function (Blueprint $t) {
            $t->dropConstrainedForeignId('movement_id');
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('hourly_rate'));
    }
};
