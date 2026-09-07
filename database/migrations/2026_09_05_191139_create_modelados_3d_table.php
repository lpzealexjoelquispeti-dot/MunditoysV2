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
        Schema::create('modelados_3d', function (Blueprint $table) {

            $table->id();

            $table->foreignId('producto_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('task_id')
                ->unique();

            $table->string('status')
                ->default('pending');

            $table->string('tipo_generacion')
                ->default('multi_image_to_3d');

            $table->string('archivo_origen')
                ->nullable();

            $table->text('prompt_text')
                ->nullable();

            $table->text('modelo_glb_path')
                ->nullable();

            $table->integer('progreso')
                ->default(0);

            $table->text('error_message')
                ->nullable();

            $table->timestamps();

            $table->softDeletes();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modelados_3d');
    }
};
