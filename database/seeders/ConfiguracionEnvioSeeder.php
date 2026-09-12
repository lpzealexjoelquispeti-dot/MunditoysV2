<?php

namespace Database\Seeders;

use App\Models\ConfiguracionEnvio;
use Illuminate\Database\Seeder;

class ConfiguracionEnvioSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ConfiguracionEnvio::query()->firstOrCreate(['id' => 1], ['habilitado' => false]);
    }
}
