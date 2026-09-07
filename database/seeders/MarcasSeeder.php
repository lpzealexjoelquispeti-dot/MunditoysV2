<?php

namespace Database\Seeders;

use App\Models\Marca;
use Illuminate\Database\Seeder;

class MarcasSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (['LEGO', 'Mattel', 'Hasbro', 'Fisher-Price', 'Play-Doh'] as $nombre) {
            Marca::query()->firstOrCreate(['nombre' => $nombre]);
        }
    }
}
