<?php

namespace App\Http\Controllers;

use App\Models\FotografiaModelado;
use App\Services\FotogrametriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FotogrametriaController extends Controller
{
    public function foto(int $modelado, int $foto, Request $request, FotogrametriaService $service): BinaryFileResponse
    {
        $sesion = $service->obtenerSesion($modelado);
        $fotografia = $sesion->fotografias()->findOrFail($foto);
        $ruta = match (true) {
            $request->boolean('mascara') => $fotografia->mascara,
            $request->boolean('original') => $fotografia->ruta,
            default => $fotografia->miniatura,
        };
        abort_unless($ruta !== null && Storage::disk('local')->exists($ruta), 404);
        $tipo = Storage::disk('local')->mimeType($ruta);
        abort_unless(in_array($tipo, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return response()->file(Storage::disk('local')->path($ruta), [
            'Content-Type' => $tipo, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function exportar(int $modelado, FotogrametriaService $service): JsonResponse
    {
        return response()->json($service->exportar($modelado), 200, [
            'Content-Disposition' => 'attachment; filename="fotogrametria-'.$modelado.'.json"',
            'Cache-Control' => 'private, no-store',
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function dataset(int $modelado, FotogrametriaService $service): StreamedResponse
    {
        $sesion = $service->obtenerSesion($modelado);
        $fotos = FotografiaModelado::query()->where('modelado_3d_id', $sesion->id)->orderBy('ranura')->orderBy('version')->get();

        return response()->streamDownload(function () use ($sesion, $fotos): void {
            $archivo = fopen('php://output', 'w');
            fputcsv($archivo, ['producto_id', 'sesion_id', 'foto_id', 'ranura', 'version', 'sha256', 'nombre', 'ancho', 'alto', 'bytes',
                'vista_manual', 'prediccion', 'apta_segun_persona', 'revisado_por', 'revisado_en', 'reemplazada_en', 'metricas', 'motivos'], escape: '');
            foreach ($fotos as $foto) {
                $nombre = preg_match('/^[=+@\-\t\r\n]/u', $foto->nombre_original) ? "'".$foto->nombre_original : $foto->nombre_original;
                fputcsv($archivo, [$sesion->producto_id, $sesion->id, $foto->id, $foto->ranura, $foto->version, $foto->sha256,
                    $nombre, $foto->ancho, $foto->alto, $foto->bytes, $foto->vista, $foto->estado,
                    $foto->etiqueta_humana === null ? '' : (int) $foto->etiqueta_humana, $foto->revisado_por, $foto->revisado_en,
                    $foto->reemplazada_en, json_encode($foto->metricas, JSON_UNESCAPED_UNICODE), json_encode($foto->motivos, JSON_UNESCAPED_UNICODE)], escape: '');
            }
            fclose($archivo);
        }, 'dataset-fotogrametria-'.$sesion->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
