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
        Schema::create('lotes_inventario', function (Blueprint $table) {

            $table->id();

            $table->foreignId('inventario_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('proveedor_id')
                ->nullable()
                ->constrained('proveedores')
                ->nullOnDelete();

            $table->string('codigo_lote')
                ->nullable();

            $table->integer('cantidad_inicial');

            $table->integer('cantidad_disponible');

            $table->decimal(
                'costo_unitario',
                10,
                2
            );

            $table->date('fecha_ingreso');

            $table->date('fecha_vencimiento')
                ->nullable();

            $table->string('estado')
                ->default('activo');

            $table->timestamps();

            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lotes_inventario');
    }
};
