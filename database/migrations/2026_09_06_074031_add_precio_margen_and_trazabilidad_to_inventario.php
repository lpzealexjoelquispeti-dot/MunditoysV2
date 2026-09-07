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
        Schema::table('lotes_inventario', function (Blueprint $table): void {
            $table->decimal('precio_venta', 10, 2)->nullable();
            $table->decimal('margen_porcentaje', 5, 2)->nullable();
            $table->uuid('clave_ingreso')->nullable()->unique();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->restrictOnDelete();
        });
        Schema::table('inventarios', function (Blueprint $table): void {
            $table->json('historial_estado')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lotes_inventario', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('registrado_por');
            $table->dropUnique(['clave_ingreso']);
            $table->dropColumn(['precio_venta', 'margen_porcentaje', 'clave_ingreso']);
        });
        Schema::table('inventarios', function (Blueprint $table): void {
            $table->dropColumn('historial_estado');
        });
    }
};
