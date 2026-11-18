<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up():void   
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('sku', 60)->unique();
            $table->enum('item_type', ['materia_prima','herramienta','producto','consumible','otro'])
                ->default('materia_prima');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unit', 40)->default('pieza');
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down():void
    {
        Schema::dropIfExists('items');
    }
};
