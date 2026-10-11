<?php

namespace App\Actions\Modelado3D;

use App\Models\Modelado3D;
use App\Models\Producto;
use App\Repositories\Modelado3DRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnlazarModeloProductoAction
{
    public function __construct(
        private Modelado3DRepository $repository,
    ) {}

    public function ejecutar(Modelado3D $modelado, int $productoId): Modelado3D
    {
        if (empty($modelado->modelo_glb_path) || ! in_array($modelado->status, ['success', 'completed'], true)) {
            throw ValidationException::withMessages([
                'modelo' => 'No se puede enlazar un modelo que aún no ha terminado de renderizarse o no tiene archivo .glb descargado.',
            ]);
        }

        return DB::transaction(function () use ($modelado, $productoId) {
            $producto = Producto::query()->findOrFail($productoId);

            // Desenlazamos cualquier modelo que tuviese previamente asignado este producto
            Modelado3D::query()
                ->where('producto_id', $producto->id)
                ->where('id', '!=', $modelado->id)
                ->where('tipo_generacion', '!=', 'fotogrametria_local')
                ->update(['producto_id' => null]);

            $modelado->update(['producto_id' => $producto->id]);

            return $modelado->fresh(['producto']);
        });
    }
}
