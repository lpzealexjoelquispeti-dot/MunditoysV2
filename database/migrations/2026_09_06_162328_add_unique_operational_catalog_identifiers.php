<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            ['sucursales', 'LOWER(TRIM(nombre))'],
            ['almacenes', 'sucursal_id, LOWER(TRIM(nombre))'],
            ['proveedores', 'LOWER(TRIM(empresa))'],
        ] as [$tabla, $expresion]) {
            if (DB::table($tabla)->selectRaw($expresion)->groupByRaw($expresion)->havingRaw('COUNT(*) > 1')->exists()) {
                throw new RuntimeException("Existen nombres duplicados en {$tabla}, incluyendo la papelera. Corrige sus nombres antes de migrar. No se borró ningún dato.");
            }
        }
        if (DB::table('proveedores')->selectRaw('TRIM(nit)')->whereNotNull('nit')->whereRaw("TRIM(nit) <> ''")
            ->groupByRaw('TRIM(nit)')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Existen NIT duplicados en proveedores. Revisa los registros antes de migrar.');
        }
        DB::statement('CREATE UNIQUE INDEX sucursales_nombre_normalizado_unique ON sucursales (LOWER(TRIM(nombre)))');
        DB::statement('CREATE UNIQUE INDEX almacenes_sucursal_nombre_unique ON almacenes (sucursal_id, LOWER(TRIM(nombre)))');
        DB::statement('CREATE UNIQUE INDEX proveedores_empresa_normalizada_unique ON proveedores (LOWER(TRIM(empresa)))');
        DB::statement("CREATE UNIQUE INDEX proveedores_nit_normalizado_unique ON proveedores (TRIM(nit)) WHERE nit IS NOT NULL AND TRIM(nit) <> ''");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX proveedores_nit_normalizado_unique');
        DB::statement('DROP INDEX proveedores_empresa_normalizada_unique');
        DB::statement('DROP INDEX almacenes_sucursal_nombre_unique');
        DB::statement('DROP INDEX sucursales_nombre_normalizado_unique');
    }
};
