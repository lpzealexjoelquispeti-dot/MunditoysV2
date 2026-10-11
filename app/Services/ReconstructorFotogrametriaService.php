<?php

namespace App\Services;

use App\Models\FotografiaModelado;
use App\Models\Modelado3D;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ReconstructorFotogrametriaService
{
    /**
     * Ejecuta COLMAP + OpenMVS mediante el orquestador local y devuelve las métricas de la reconstrucción.
     *
     * @param  callable(string, int): void  $alAvanzar  Recibe la etapa terminada y el porcentaje acumulado.
     * @return array<string, mixed>
     */
    public function reconstruir(Modelado3D $sesion, string $espacioTrabajo, string $rutaGlb, callable $alAvanzar): array
    {
        $sesion->loadMissing('fotografias');
        $frente = $sesion->fotografias->where('vista', 'frente')->sortBy('ranura')->first() ?? $sesion->fotografias->sortBy('ranura')->first();
        $entrada = [
            'workspace' => $espacioTrabajo,
            'output_path' => $rutaGlb,
            'colmap' => config('fotogrametria.motores.colmap'),
            'openmvs' => config('fotogrametria.motores.openmvs'),
            'front_image_id' => $frente?->id,
            'options' => [...config('fotogrametria.reconstruccion.parametros'), 'capture_mode' => $sesion->modo_captura],
            'segmentation_model' => config('fotogrametria.segmentacion.habilitada') ? config('fotogrametria.segmentacion.modelo') : null,
            'images' => $sesion->fotografias->map(fn (FotografiaModelado $foto): array => [
                'id' => $foto->id, 'slot' => $foto->ranura, 'path' => Storage::disk('local')->path($foto->ruta),
                'mask' => $foto->mascara ? Storage::disk('local')->path($foto->mascara) : null,
            ])->values()->all(),
        ];

        $pendiente = '';
        $proceso = Process::timeout((int) config('fotogrametria.reconstruccion.timeout'))
            ->input(json_encode($entrada, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))
            ->run([config('fotogrametria.python'), '-B', app_path('Services/Fotogrametria/reconstruir_modelo.py')],
                function (string $tipo, string $salida) use (&$pendiente, $alAvanzar): void {
                    if ($tipo !== 'out') {
                        return;
                    }
                    $pendiente .= $salida;
                    while (($fin = strpos($pendiente, "\n")) !== false) {
                        $evento = json_decode(substr($pendiente, 0, $fin), true);
                        $pendiente = substr($pendiente, $fin + 1);
                        if (($evento['event'] ?? null) === 'progress') {
                            $alAvanzar((string) $evento['stage'], (int) $evento['progress']);
                        }
                    }
                });

        $eventos = collect(preg_split('/\R/', $proceso->output()))
            ->map(fn (string $linea): mixed => json_decode($linea, true))
            ->filter(fn (mixed $evento): bool => is_array($evento) && isset($evento['event']));

        if ($error = $eventos->firstWhere('event', 'error')) {
            throw new RuntimeException(is_string($error['message'] ?? null) ? $error['message'] : 'La reconstrucción local falló.');
        }

        $resultado = $eventos->firstWhere('event', 'result');
        if (! $proceso->successful() || ! is_array($resultado)
            || ! is_int($resultado['glb_bytes'] ?? null) || ! is_int($resultado['registered_images'] ?? null)
            || ! is_int($resultado['faces'] ?? null) || ! is_int($resultado['duration_ms'] ?? null)) {
            throw new RuntimeException('La reconstrucción local no terminó correctamente. Revisa el registro de la reconstrucción e inténtalo nuevamente.');
        }
        if (! is_file($rutaGlb) || filesize($rutaGlb) !== $resultado['glb_bytes']) {
            throw new RuntimeException('La reconstrucción terminó, pero no se encontró el archivo GLB generado.');
        }

        return $resultado;
    }
}
