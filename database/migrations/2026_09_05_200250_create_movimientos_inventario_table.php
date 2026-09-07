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
        Schema::create('movimientos_inventario', function (Blueprint $table) {

            $table->id();

            $table->foreignId('lote_id')
                ->constrained('lotes_inventario')
                ->cascadeOnDelete();

            $table->foreignId('usuario_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('tipo_movimiento');

            $table->integer('cantidad');

            $table->integer('cantidad_anterior');

            $table->integer('cantidad_nueva');

            $table->text('motivo')
                ->nullable();

            $table->string('referencia_tipo')
                ->nullable();

            $table->unsignedBigInteger('referencia_id')
                ->nullable();

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
