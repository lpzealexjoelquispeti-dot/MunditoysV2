<?php

namespace App\Repositories;

use App\Models\Modelado3D;
use App\Models\Producto;

class ProductoMediaRepository
{
    /** @param array<int, string> $rutas */
    public function agregarImagenes(Producto $producto, array $rutas): void
    {
        $ultimoOrden = (int) $producto->imagenes()->max('orden');
        $tienePrincipal = $producto->imagenes()->where('principal', true)->exists();

        foreach ($rutas as $indice => $ruta) {
            $producto->imagenes()->create([
                'ruta_archivo' => $ruta,
                'tipo_imagen' => 'producto',
                'orden' => $ultimoOrden + $indice + 1,
                'principal' => ! $tienePrincipal && $indice === 0,
            ]);
        }
    }

    /** @param array<int, int> $imagenIds */
    public function eliminarImagenes(Producto $producto, array $imagenIds): void
    {
        if ($imagenIds === []) {
            return;
        }

        $producto->imagenes()->whereIn('id', $imagenIds)->delete();
        $primeraImagen = $producto->imagenes()->orderBy('orden')->first();

        if ($primeraImagen !== null && ! $producto->imagenes()->where('principal', true)->exists()) {
            $primeraImagen->update(['principal' => true]);
        }
    }

    public function guardarModeloGlb(Producto $producto, string $ruta): Modelado3D
    {
        $producto->modelos3D()->delete();

        return $producto->modelos3D()->create([
            'task_id' => 'manual-'.str()->ulid(),
            'status' => 'completed',
            'tipo_generacion' => 'carga_manual',
            'modelo_glb_path' => $ruta,
            'progreso' => 100,
        ]);
    }
}
