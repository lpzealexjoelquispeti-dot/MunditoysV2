<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vectores de significado (embeddings de bge-m3) de productos y de la base de conocimiento.
     */
    public function up(): void
    {
        Schema::create('vectores_ia', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 30);
            $table->unsignedBigInteger('referencia_id');
            $table->string('modelo', 60);
            $table->char('huella', 40);
            $table->json('vector');
            $table->timestamps();
            $table->unique(['tipo', 'referencia_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vectores_ia');
    }
};
