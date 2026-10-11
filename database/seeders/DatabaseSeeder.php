<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PermisosSeeder::class,
            RolesSeeder::class,
            AdminSeeder::class,
            CategoriasSeeder::class,
            MarcasSeeder::class,
            MarvelCatalogSeeder::class,
            VentasReportesSeeder::class,
            HistorialJuguetesSeeder::class,
            BaseConocimientoIASeeder::class,
        ]);
    }
}
