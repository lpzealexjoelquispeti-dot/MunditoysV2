<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['categorias', 'marcas'] as $tabla) {
            $duplicados = DB::table($tabla)->selectRaw('LOWER(TRIM(nombre)) AS nombre_normalizado')
                ->groupByRaw('LOWER(TRIM(nombre))')->havingRaw('COUNT(*) > 1')->exists();
            if ($duplicados) {
                throw new RuntimeException("Existen nombres duplicados en {$tabla}, incluyendo la papelera. Revisa sus nombres antes de ejecutar esta migración. No se eliminó ningún registro.");
            }
        }
        DB::statement('CREATE UNIQUE INDEX categorias_nombre_normalizado_unique ON categorias (LOWER(TRIM(nombre)))');
        DB::statement('CREATE UNIQUE INDEX marcas_nombre_normalizado_unique ON marcas (LOWER(TRIM(nombre)))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX marcas_nombre_normalizado_unique');
        DB::statement('DROP INDEX categorias_nombre_normalizado_unique');
    }
};
