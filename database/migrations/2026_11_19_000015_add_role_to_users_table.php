<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 'admin' = dueño del taller, 'operario' = personal del taller
            $table->string('role', 20)->default('operario')->after('password');
            // Permite dar de baja a alguien sin borrar el historial que dejó registrado
            $table->boolean('is_active')->default(true)->after('role');
        });

        // Si ya había usuarios (la base se creó con el registro abierto), el primero
        // queda como administrador para no dejar el sistema sin nadie que lo gestione.
        $firstId = DB::table('users')->min('id');
        if ($firstId !== null) {
            DB::table('users')->where('id', $firstId)->update(['role' => 'admin']);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
