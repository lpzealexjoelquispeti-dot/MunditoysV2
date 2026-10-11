<?php

namespace App\Actions\Modelado3D;

use App\Models\Modelado3D;
use App\Models\ReconstruccionFotogrametria;
use App\Repositories\FotogrametriaRepository;
use App\Services\ReconstructorFotogrametriaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ReconstruirModeloJob implements ShouldQueue
{
    use Queueable;

    public int $timeout;

    public int $tries = 60;

    public int $maxExceptions = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $reconstruccionId, public string $token)
    {
        $this->timeout = (int) config('fotogrametria.reconstruccion.timeout') + 60;
        $this->onConnection('fotogrametria_reconstruccion');
        $this->onQueue(config('fotogrametria.reconstruccion.cola'));
    }

    public function handle(ReconstructorFotogrametriaService $reconstructor, FotogrametriaRepository $repository): void
    {
        $candado = Cache::lock('fotogrametria:reconstruccion-local', $this->timeout + 60);
        if (! $candado->get()) {
            $this->release(30);

            return;
        }

        $rutaGlb = null;
        try {
            $reconstruccion = DB::transaction(function (): ?ReconstruccionFotogrametria {
                $reconstruccion = ReconstruccionFotogrametria::query()->lockForUpdate()->find($this->reconstruccionId);
                if (! $this->esVigente($reconstruccion, 'en_cola')) {
                    return null;
                }
                $reconstruccion->update(['estado' => 'procesando', 'etapa' => 'preparacion', 'progreso' => 1, 'iniciado_en' => now()]);

                return $reconstruccion;
            });
            if ($reconstruccion === null) {
                return;
            }

            $sesion = $repository->obtener($reconstruccion->modelado_3d_id);
            $rutaGlb = config('fotogrametria.reconstruccion.directorio_modelos').'/sesion-'.$sesion->id.'-r'.$reconstruccion->revision.'-'.Str::lower((string) Str::ulid()).'.glb';
            $espacioTrabajo = Storage::disk('local')->path('fotogrametria/'.$sesion->id.'/reconstrucciones/'.$reconstruccion->id);

            $resultado = $reconstructor->reconstruir($sesion, $espacioTrabajo, Storage::disk('public')->path($rutaGlb),
                function (string $etapa, int $progreso) use ($reconstruccion): void {
                    ReconstruccionFotogrametria::query()->whereKey($reconstruccion->id)->where('token', $this->token)
                        ->where('estado', 'procesando')->update(['etapa' => $etapa, 'progreso' => min(99, $progreso), 'updated_at' => now()]);
                });

            $publicado = DB::transaction(function () use ($resultado, $rutaGlb, $sesion): bool {
                $actual = ReconstruccionFotogrametria::query()->lockForUpdate()->find($this->reconstruccionId);
                if (! $this->esVigente($actual, 'procesando')) {
                    return false;
                }
                $modelo = Modelado3D::query()->create([
                    'task_id' => 'local-reconstruccion-'.$actual->id,
                    'tipo_generacion' => 'fotogrametria',
                    'status' => 'completed',
                    'modelo_glb_path' => $rutaGlb,
                    'progreso' => 100,
                    'sesion_origen_id' => $sesion->id,
                ]);
                $actual->update([
                    'estado' => 'completado', 'etapa' => 'empaquetado', 'progreso' => 100, 'resultado' => $resultado,
                    'modelo_generado_id' => $modelo->id, 'duracion_ms' => $resultado['duration_ms'],
                    'finalizado_en' => now(), 'mensaje_error' => null,
                ]);

                return true;
            });
            if (! $publicado) {
                Storage::disk('public')->delete($rutaGlb);
            }
        } catch (Throwable $exception) {
            report($exception);
            if ($rutaGlb !== null) {
                Storage::disk('public')->delete($rutaGlb);
            }
            $this->marcarError(match (true) {
                $exception instanceof ProcessTimedOutException => 'La reconstrucción superó el tiempo máximo permitido. Revisa que la GPU esté disponible e inténtalo nuevamente.',
                $exception instanceof RuntimeException => $exception->getMessage(),
                default => null,
            });
        } finally {
            $candado->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->marcarError(null);
    }

    private function marcarError(?string $mensaje): void
    {
        ReconstruccionFotogrametria::query()->whereKey($this->reconstruccionId)->where('token', $this->token)
            ->whereIn('estado', ['en_cola', 'procesando'])
            ->update([
                'estado' => 'error',
                'mensaje_error' => $mensaje ?: 'La reconstrucción local no terminó. Las fotografías se conservaron; puedes volver a intentarlo.',
                'finalizado_en' => now(),
            ]);
    }

    private function esVigente(?ReconstruccionFotogrametria $reconstruccion, string $estado): bool
    {
        if ($reconstruccion === null || $reconstruccion->token !== $this->token || $reconstruccion->estado !== $estado) {
            return false;
        }

        return Modelado3D::query()->whereKey($reconstruccion->modelado_3d_id)
            ->where('tipo_generacion', 'fotogrametria_local')->where('revision', $reconstruccion->revision)->exists();
    }
}
