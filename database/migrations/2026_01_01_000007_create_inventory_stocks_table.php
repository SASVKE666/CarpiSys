<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up():void
    {
       Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 12, 4)->default(0);
            $table->decimal('reserved_qty', 12, 4)->default(0);
            $table->decimal('min_quantity', 12, 4)->default(0);
            $table->decimal('avg_cost', 12, 2)->default(0);
            $table->timestamp('updated_at')->nullable();
            $table->unique(['item_id', 'location_id']);
        });
    }

    public function down():void
    {
        Schema::dropIfExists('inventory_stocks');
    }
};