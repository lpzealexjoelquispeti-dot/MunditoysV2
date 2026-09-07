<?php

namespace Database\Factories;

use App\Models\Inventario;
use App\Models\LoteInventario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoteInventario>
 */
class LoteInventarioFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inventario_id' => Inventario::factory(),
            'codigo_lote' => 'MTL-'.fake()->unique()->uuid(),
            'cantidad_inicial' => 10,
            'cantidad_disponible' => 10,
            'costo_unitario' => '80.00',
            'precio_venta' => '100.00',
            'margen_porcentaje' => '20.00',
            'fecha_ingreso' => today(),
            'estado' => 'activo',
        ];
    }
}
