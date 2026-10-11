<?php

namespace App\Actions\Modelado3D;

use App\Models\FotografiaModelado;
use App\Services\SegmentadorFotogrametriaService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Calcula en segundo plano las máscaras de IA de las fotos de una sesión, mientras se siguen subiendo.
 */
class SegmentarFotografiasJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $timeout;

    public int $tries = 60;

    public int $maxExceptions = 1;

    public function __construct(public int $sesionId)
    {
        $this->timeout = (int) config('fotogrametria.segmentacion.timeout') + 60;
        $this->onConnection('fotogrametria_reconstruccion');
        $this->onQueue(config('fotogrametria.reconstruccion.cola'));
    }

    public function uniqueId(): string
    {
        return (string) $this->sesionId;
    }

    public function handle(SegmentadorFotogrametriaService $segmentador): void
    {
        if (! $segmentador->disponible()) {
            return;
        }
        $candado = Cache::lock('fotogrametria:reconstruccion-local', $this->timeout + 60);
        if (! $candado->get()) {
            $this->release(30);

            return;
        }

        $procesadas = [];
        try {
            $pendientes = $this->pendientes();
            $procesadas = $pendientes->modelKeys();
            if ($pendientes->isNotEmpty()) {
                $segmentador->segmentar($pendientes);
            }
        } catch (Throwable $exception) {
            report($exception);
        } finally {
            $candado->release();
        }

        if ($this->pendientes()->whereNotIn('id', $procesadas)->isNotEmpty()) {
            self::dispatch($this->sesionId);
        }
    }

    /** @return Collection<int, FotografiaModelado> */
    private function pendientes(): Collection
    {
        return FotografiaModelado::query()->where('modelado_3d_id', $this->sesionId)
            ->whereNull('reemplazada_en')->whereNull('mascara')->orderBy('ranura')->get();
    }
}
