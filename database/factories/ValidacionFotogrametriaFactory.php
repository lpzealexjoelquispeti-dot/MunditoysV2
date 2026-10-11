<?php

namespace Database\Factories;

use App\Models\Modelado3D;
use App\Models\ValidacionFotogrametria;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ValidacionFotogrametria>
 */
class ValidacionFotogrametriaFactory extends Factory
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
            'version_algoritmo' => 'opencv-sift-v1',
            'parametros' => ['analysis_size' => 1024],
            'resultado' => ['images' => [], 'pairs' => [], 'suggested_order' => []],
            'duracion_ms' => 100,
        ];
    }
}
