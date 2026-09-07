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
        Schema::create('ventas', function (Blueprint $table) {

            $table->id();

            $table->string('numero_venta')
                ->unique();

            $table->foreignId('cliente_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('usuario_vendedor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('tipo_venta')
                ->default('online');

            $table->string('tipo_entrega')
                ->default('delivery');

            $table->string('estado')
                ->default('pendiente');

            $table->decimal(
                'subtotal',
                10,
                2
            );

            $table->decimal(
                'descuento',
                10,
                2
            )
                ->default(0);

            $table->decimal(
                'total',
                10,
                2
            );

            $table->timestamp('fecha_venta')
                ->nullable();

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
