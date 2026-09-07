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
        Schema::create('producto_imagenes', function (Blueprint $table) {

            $table->id();

            $table->foreignId('producto_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('ruta_archivo');

            $table->string('tipo_imagen')
                ->nullable();

            $table->integer('orden')
                ->default(1);

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
        Schema::dropIfExists('producto_imagenes');
    }
};
