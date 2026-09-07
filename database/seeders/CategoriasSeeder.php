<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Seeder;

class CategoriasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            'Acción y aventuras',
            'Construcción',
            'Educativos',
            'Muñecas y figuras',
            'Primera infancia',
        ] as $nombre) {
            Categoria::query()->firstOrCreate(
                ['nombre' => $nombre],
                ['estado' => true],
            );
        }
    }
}
