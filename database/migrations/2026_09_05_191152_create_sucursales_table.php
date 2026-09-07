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
        Schema::create('sucursales', function (Blueprint $table) {

            $table->id();

            $table->string('nombre');

            $table->text('direccion')
                ->nullable();

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

            $table->string('telefono')
                ->nullable();

            $table->boolean('estado')
                ->default(true);

            $table->timestamps();

            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sucursales');
    }
};
