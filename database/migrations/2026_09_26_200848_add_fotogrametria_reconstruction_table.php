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
            $table->foreignId('sesion_origen_id')->nullable()->constrained('modelados_3d')->nullOnDelete();
        });

        Schema::create('reconstrucciones_fotogrametria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('modelado_3d_id')->constrained('modelados_3d')->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('estado')->default('en_cola');
            $table->string('etapa')->nullable();
            $table->unsignedTinyInteger('progreso')->default(0);
            $table->uuid('token');
            $table->text('mensaje_error')->nullable();
            $table->json('parametros');
            $table->json('resultado')->nullable();
            $table->foreignId('modelo_generado_id')->nullable()->constrained('modelados_3d')->nullOnDelete();
            $table->unsignedInteger('duracion_ms')->nullable();
            $table->timestamp('iniciado_en')->nullable();
            $table->timestamp('finalizado_en')->nullable();
            $table->timestamps();
            $table->index(['modelado_3d_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconstrucciones_fotogrametria');
        Schema::table('modelados_3d', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sesion_origen_id');
        });
    }
};
