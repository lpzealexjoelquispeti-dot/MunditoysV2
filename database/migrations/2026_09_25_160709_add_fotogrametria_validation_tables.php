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
        Schema::table('modelados_3d', function (Blueprint $table): void {
            $table->unsignedBigInteger('referencia_foto_id')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->unsignedInteger('revision_analizada')->nullable();
            $table->json('resultado_validacion')->nullable();
            $table->timestamp('analizado_en')->nullable();
            $table->uuid('validacion_token')->nullable();
        });

        Schema::create('fotografias_modelado', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('modelado_3d_id')->constrained('modelados_3d')->cascadeOnDelete();
            $table->unsignedSmallInteger('ranura');
            $table->unsignedInteger('version')->default(1);
            $table->string('nombre_original');
            $table->string('ruta');
            $table->string('miniatura');
            $table->string('sha256', 64);
            $table->unsignedInteger('ancho');
            $table->unsignedInteger('alto');
            $table->unsignedBigInteger('bytes');
            $table->string('estado')->default('pendiente');
            $table->string('vista')->default('sin_clasificar');
            $table->json('motivos')->nullable();
            $table->json('metricas')->nullable();
            $table->boolean('etiqueta_humana')->nullable();
            $table->foreignId('revisado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable();
            $table->timestamp('reemplazada_en')->nullable();
            $table->timestamps();
            $table->unique(['modelado_3d_id', 'ranura', 'version'], 'fotografia_ranura_version_unique');
            $table->index(['modelado_3d_id', 'reemplazada_en']);
        });

        Schema::create('validaciones_fotogrametria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('modelado_3d_id')->constrained('modelados_3d')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('version_algoritmo');
            $table->json('parametros');
            $table->json('resultado');
            $table->unsignedInteger('duracion_ms');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('validaciones_fotogrametria');
        Schema::dropIfExists('fotografias_modelado');
        Schema::table('modelados_3d', function (Blueprint $table): void {
            $table->dropColumn(['referencia_foto_id', 'revision', 'revision_analizada', 'resultado_validacion', 'analizado_en', 'validacion_token']);
        });
    }
};
