<?php

namespace Database\Factories;

use App\Models\ConfiguracionEnvio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConfiguracionEnvio>
 */
class ConfiguracionEnvioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'habilitado' => false,
            'tarifa_km_usd' => null,
            'minimo_usd' => null,
            'cambio_paralelo' => null,
        ];
    }
}
