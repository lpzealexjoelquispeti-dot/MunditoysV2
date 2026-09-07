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
        Schema::create('auditorias', function (Blueprint $table) {

            $table->id();

            $table->foreignId('usuario_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('modulo_afectado');

            $table->string('accion');

            $table->string('tabla_afectada');

            $table->unsignedBigInteger('registro_id')
                ->nullable();

            $table->json('datos_anterior_jsonb')
                ->nullable();

            $table->json('datos_nuevo_jsonb')
                ->nullable();

            $table->string('ip')
                ->nullable();

            $table->text('user_agent')
                ->nullable();

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auditorias');
    }
};
