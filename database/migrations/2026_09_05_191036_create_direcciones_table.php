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
        Schema::create('direcciones', function (Blueprint $table) {

            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('nombre_referencia')
                ->nullable();

            $table->text('direccion_completa');

            $table->string('zona')
                ->nullable();

            $table->string('ciudad')
                ->nullable();

            $table->decimal(
                'latitud',
                10,
                7
            )
                ->nullable();

            $table->decimal(
                'longitud',
                10,
                7
            )
                ->nullable();

            $table->json('ubicacion_jsonb')
                ->nullable();

            $table->boolean('principal')
                ->default(false);

            $table->timestamps();

            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('direcciones');
    }
};
