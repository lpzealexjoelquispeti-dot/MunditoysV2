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
        Schema::create('mensajes_ia', function (Blueprint $table) {

            $table->id();

            $table->foreignId('conversacion_id')
                ->constrained('conversaciones_ia')
                ->cascadeOnDelete();

            $table->string('rol');

            $table->text('mensaje');

            $table->string('modelo')
                ->nullable();

            $table->integer('tokens')
                ->nullable();

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mensajes_ia');
    }
};
