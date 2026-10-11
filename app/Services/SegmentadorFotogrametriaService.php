<?php

namespace App\Services;

use App\Models\FotografiaModelado;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SegmentadorFotogrametriaService
{
    public function disponible(): bool
    {
        return (bool) config('fotogrametria.segmentacion.habilitada')
            && is_file(config('fotogrametria.segmentacion.modelo'))
            && is_file(config('fotogrametria.python'));
    }

    /**
     * Genera con BiRefNet la máscara del juguete de cada fotografía y la guarda junto a la original.
     *
     * @param  Collection<int, FotografiaModelado>  $fotos
     * @return array{mascaras: int, proveedor: ?string}
     */
    public function segmentar(Collection $fotos): array
    {
        $destinos = $fotos->mapWithKeys(fn (FotografiaModelado $foto): array => [
            $foto->id => 'fotogrametria/'.$foto->modelado_3d_id.'/mascaras/'.$foto->id.'.png',
        ]);
        $entrada = [
            'model' => config('fotogrametria.segmentacion.modelo'),
            'images' => $fotos->map(fn (FotografiaModelado $foto): array => [
                'id' => $foto->id,
                'path' => Storage::disk('local')->path($foto->ruta),
                'output' => Storage::disk('local')->path($destinos[$foto->id]),
            ])->values()->all(),
        ];

        $proceso = Process::timeout((int) config('fotogrametria.segmentacion.timeout'))
            ->input(json_encode($entrada, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))
            ->run([config('fotogrametria.python'), '-B', app_path('Services/Fotogrametria/segmentar_juguete.py')]);

        $eventos = collect(preg_split('/\R/', $proceso->output()))
            ->map(fn (string $linea): mixed => json_decode($linea, true))
            ->filter(fn (mixed $evento): bool => is_array($evento) && isset($evento['event']));
        if (! $proceso->successful() || $eventos->contains('event', 'error')) {
            throw new RuntimeException('La segmentación local de las fotografías no pudo completarse.');
        }

        $guardadas = 0;
        foreach ($eventos->where('event', 'mask') as $evento) {
            $foto = $fotos->firstWhere('id', $evento['id']);
            if ($foto === null || ! Storage::disk('local')->exists($destinos[$foto->id])) {
                continue;
            }
            $foto->update(['mascara' => $destinos[$foto->id], 'fraccion_mascara' => (float) ($evento['foreground'] ?? 0)]);
            $guardadas++;
        }

        return ['mascaras' => $guardadas, 'proveedor' => $eventos->firstWhere('event', 'result')['provider'] ?? null];
    }
}
