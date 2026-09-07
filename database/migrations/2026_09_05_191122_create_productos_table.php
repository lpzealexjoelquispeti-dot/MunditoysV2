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
        Schema::create('productos', function (Blueprint $table) {

            $table->id();

            $table->foreignId('categoria_id')
                ->constrained()
                ->restrictOnDelete();

            $table->foreignId('marca_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('codigo_producto')
                ->unique();

            $table->string('codigo_barra')
                ->nullable()
                ->unique();

            $table->string('nombre');

            $table->text('descripcion')
                ->nullable();

            $table->decimal(
                'precio_compra_referencia',
                10,
                2
            )
                ->nullable();

            $table->decimal(
                'precio_venta',
                10,
                2
            );

            $table->string('edad_recomendada')
                ->nullable();

            $table->decimal(
                'peso',
                8,
                2
            )
                ->nullable();

            $table->string('dimensiones')
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
        Schema::dropIfExists('productos');
    }
};
