<?php

namespace Database\Factories;

use App\Models\FotografiaModelado;
use App\Models\Modelado3D;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FotografiaModelado>
 */
class FotografiaModeladoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'modelado_3d_id' => Modelado3D::factory(),
            'ranura' => 1,
            'version' => 1,
            'nombre_original' => 'captura-'.fake()->uuid().'.jpg',
            'ruta' => 'fotogrametria/'.fake()->uuid().'.jpg',
            'miniatura' => 'fotogrametria/'.fake()->uuid().'-miniatura.jpg',
            'sha256' => hash('sha256', fake()->uuid()),
            'ancho' => 1024, 'alto' => 768, 'bytes' => 150000,
            'estado' => 'pendiente', 'vista' => 'sin_clasificar',
        ];
    }

    public function compatible(): static
    {
        return $this->state(fn (): array => ['estado' => 'compatible', 'vista' => 'frente']);
    }
}
