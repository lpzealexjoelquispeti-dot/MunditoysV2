<?php

namespace App\Actions\Modelado3D;

use App\Models\Modelado3D;
use App\Services\AnalizadorFotogrametriaService;
use App\Services\FotogrametriaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class ValidarFotografiasJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 330;

    public int $tries = 50;

    public bool $failOnTimeout = true;

    public function __construct(public int $modeladoId, public int $revision, public string $token)
    {
        $this->onConnection('fotogrametria');
        $this->onQueue(config('fotogrametria.cola'));
    }

    public function handle(AnalizadorFotogrametriaService $analizador, FotogrametriaService $fotogrametria): void
    {
        $candado = Cache::lock('fotogrametria:analisis-local', $this->timeout + 30);
        if (! $candado->get()) {
            $this->release(10);

            return;
        }

        try {
            $sesion = DB::transaction(function (): ?Modelado3D {
                $sesion = Modelado3D::query()->lockForUpdate()->find($this->modeladoId);
                if (! $this->esVigente($sesion) || $sesion->status !== 'en_cola') {
                    return null;
                }
                $sesion->update(['status' => 'analizando', 'progreso' => 0]);

                return $sesion->load('fotografias');
            });
            if ($sesion === null) {
                return;
            }

            $resultado = $analizador->analizar($sesion);
            DB::transaction(function () use ($resultado, $fotogrametria): void {
                $actual = Modelado3D::query()->lockForUpdate()->find($this->modeladoId);
                if (! $this->esVigente($actual) || $actual->status !== 'analizando') {
                    return;
                }
                foreach ($resultado['images'] as $foto) {
                    $actual->fotografias()->findOrFail($foto['id'])->update([
                        'estado' => $foto['status'], 'motivos' => $foto['reasons'], 'metricas' => $foto['metrics'],
                    ]);
                }
                $actual->validaciones()->create([
                    'revision' => $this->revision, 'version_algoritmo' => $resultado['version'],
                    'parametros' => $resultado['parameters'], 'resultado' => $resultado, 'duracion_ms' => $resultado['duration_ms'],
                ]);
                $actual->update(['revision_analizada' => $this->revision, 'resultado_validacion' => $resultado,
                    'analizado_en' => now(), 'progreso' => 100, 'error_message' => null]);
                $fotogrametria->actualizarEstadoColeccion($actual->load('fotografias'));
            });
        } catch (Throwable $exception) {
            report($exception);
            $this->failed($exception);
        } finally {
            $candado->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        Modelado3D::query()->whereKey($this->modeladoId)->where('revision', $this->revision)
            ->where('validacion_token', $this->token)->whereIn('status', ['en_cola', 'analizando'])
            ->update(['status' => 'error', 'progreso' => 0, 'error_message' => 'La validación local no terminó. Las fotos se conservaron; puedes reintentar el análisis.']);
    }

    private function esVigente(?Modelado3D $sesion): bool
    {
        return $sesion !== null && $sesion->tipo_generacion === 'fotogrametria_local'
            && $sesion->revision === $this->revision && $sesion->validacion_token === $this->token;
    }
}
