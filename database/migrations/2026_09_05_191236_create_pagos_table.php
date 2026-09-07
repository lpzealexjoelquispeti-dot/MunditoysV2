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
        Schema::create('pagos', function (Blueprint $table) {

            $table->id();

            $table->foreignId('venta_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('metodo_pago');

            $table->string('referencia_pago')
                ->nullable();

            $table->string('stripe_payment_id')
                ->nullable();

            $table->string('estado')
                ->default('pendiente');

            $table->decimal(
                'monto',
                10,
                2
            );

            $table->timestamp('fecha_pago')
                ->nullable();

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pagos');
    }
};
