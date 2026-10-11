<?php

namespace App\Services;

use App\Models\FotografiaModelado;
use App\Models\Modelado3D;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AnalizadorFotogrametriaService
{
    /** @return array<string, mixed> */
    public function analizar(Modelado3D $sesion): array
    {
        $sesion->loadMissing('fotografias');
        $entrada = [
            'reference_id' => $sesion->referencia_foto_id,
            'options' => config('fotogrametria.parametros'),
            'images' => $sesion->fotografias->map(fn (FotografiaModelado $foto): array => [
                'id' => $foto->id, 'path' => Storage::disk('local')->path($foto->ruta), 'sha256' => $foto->sha256,
            ])->all(),
        ];

        $proceso = Process::timeout(config('fotogrametria.timeout'))
            ->input(json_encode($entrada, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))
            ->run([config('fotogrametria.python'), '-B', app_path('Services/Fotogrametria/analizar_imagenes.py')]);

        if (! $proceso->successful()) {
            throw new RuntimeException('El analizador local no pudo completar la validación. Verifica el entorno Python y las fotografías e inténtalo nuevamente.');
        }

        $resultado = json_decode($proceso->output(), true, flags: JSON_THROW_ON_ERROR);
        $ids = $sesion->fotografias->pluck('id')->sort()->values()->all();
        if (! is_array($resultado) || isset($resultado['error']) || ! is_array($resultado['images'] ?? null)
            || collect($resultado['images'])->pluck('id')->sort()->values()->all() !== $ids
            || ($resultado['reference_id'] ?? null) !== $sesion->referencia_foto_id
            || ! is_string($resultado['version'] ?? null) || ! is_array($resultado['pairs'] ?? null)
            || ! is_array($resultado['parameters'] ?? null) || ! is_array($resultado['suggested_order'] ?? null)
            || ! is_int($resultado['duration_ms'] ?? null) || $resultado['duration_ms'] < 0) {
            throw new RuntimeException('El analizador devolvió un resultado incompleto. La colección sigue pendiente de validación.');
        }

        foreach ($resultado['images'] as $foto) {
            if (! in_array($foto['status'] ?? null, ['compatible', 'review', 'rejected'], true)
                || ! is_array($foto['reasons'] ?? null) || ! is_array($foto['metrics'] ?? null)) {
                throw new RuntimeException('El diagnóstico de una fotografía no es válido.');
            }
        }

        return $resultado;
    }
}
