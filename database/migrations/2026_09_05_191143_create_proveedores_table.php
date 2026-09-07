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
        Schema::create('proveedores', function (Blueprint $table) {

            $table->id();

            $table->string('empresa');

            $table->string('nombre_contacto')
                ->nullable();

            $table->string('telefono')
                ->nullable();

            $table->string('email')
                ->nullable();

            $table->text('direccion')
                ->nullable();

            $table->string('nit')
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
        Schema::dropIfExists('proveedores');
    }
};
