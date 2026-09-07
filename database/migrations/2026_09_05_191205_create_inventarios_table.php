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
        Schema::create('inventarios', function (Blueprint $table) {

            $table->id();

            $table->foreignId('producto_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('almacen_id')
                ->constrained('almacenes')
                ->cascadeOnDelete();

            $table->integer('stock_minimo')
                ->default(0);

            $table->timestamps();

            $table->softDeletes();

            $table->unique([
                'producto_id',
                'almacen_id',
            ]);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventarios');
    }
};
