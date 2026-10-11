<?php

namespace Database\Factories;

use App\Models\Modelado3D;
use App\Models\ReconstruccionFotogrametria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReconstruccionFotogrametria>
 */
class ReconstruccionFotogrametriaFactory extends Factory
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
            'revision' => 1,
            'estado' => 'en_cola',
            'progreso' => 0,
            'token' => fake()->uuid(),
            'parametros' => ['max_image_size' => 2000],
        ];
    }

    public function completada(): static
    {
        return $this->state(fn (): array => [
            'estado' => 'completado', 'etapa' => 'empaquetado', 'progreso' => 100,
            'modelo_generado_id' => Modelado3D::factory()->manual()->state(['tipo_generacion' => 'fotogrametria', 'producto_id' => null]),
            'resultado' => ['registered_images' => 48, 'total_images' => 48, 'faces' => 150000, 'glb_bytes' => 6858984, 'reprojection_error' => 0.74],
            'duracion_ms' => 249000, 'iniciado_en' => now()->subMinutes(5), 'finalizado_en' => now(),
        ]);
    }
}
