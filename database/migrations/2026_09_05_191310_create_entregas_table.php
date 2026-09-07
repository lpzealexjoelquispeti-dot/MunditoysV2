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
        Schema::create('entregas', function (Blueprint $table) {

            $table->id();

            $table->foreignId('venta_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('direccion_id')
                ->nullable()
                ->constrained('direcciones')
                ->nullOnDelete();

            $table->foreignId('empleado_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('sucursal_origen_id')
                ->nullable()
                ->constrained('sucursales')
                ->nullOnDelete();

            $table->date('fecha_programada')
                ->nullable();

            $table->time('hora_inicio')
                ->nullable();

            $table->time('hora_fin')
                ->nullable();

            $table->string('estado')
                ->default('pendiente');

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entregas');
    }
};
