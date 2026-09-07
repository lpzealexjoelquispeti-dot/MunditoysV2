<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TripoService
{
    protected ?string $apiKey;

    protected string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.tripo.api_key') ?: null;
        $this->apiUrl = rtrim(config('services.tripo.api_url', 'https://api.tripo3d.ai/v2/openapi'), '/');
    }

    /**
     * Valida que la API key esté configurada antes de hacer peticiones.
     */
    protected function asegurarApiKey(): string
    {
        if (empty($this->apiKey)) {
            throw ValidationException::withMessages([
                'fotos' => 'La clave TRIPO_API_KEY no está configurada en el archivo .env. Configúrala para generar modelos 3D.',
            ]);
        }

        return $this->apiKey;
    }

    /**
     * Sube una imagen individual a los servidores de Tripo y retorna el image_token.
     */
    public function subirImagen(string $rutaArchivo): string
    {
        $apiKey = $this->asegurarApiKey();

        if (! file_exists($rutaArchivo)) {
            throw new Exception("El archivo de imagen no existe en la ruta: {$rutaArchivo}");
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
            ])->attach(
                'file',
                file_get_contents($rutaArchivo),
                basename($rutaArchivo)
            )->post($this->apiUrl.'/upload');

            if ($response->failed()) {
                Log::error('Tripo [Upload] Error HTTP: '.$response->body());
                throw new Exception('Error al subir imagen a Tripo: '.$response->body());
            }

            $token = $response->json('data.image_token');

            if (! $token) {
                Log::error('Tripo [Upload] Respuesta sin token: '.$response->body());
                throw new Exception('Tripo no devolvió image_token.');
            }

            return $token;
        } catch (Exception $e) {
            Log::error('TripoService [subirImagen] Error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Genera una tarea de multiview_to_model a partir de un arreglo de 2 a 4 rutas de imágenes.
     *
     * @param  array<int, string>  $rutasArchivos
     * @return array<string, mixed>
     */
    public function generarMultiImageTo3d(array $rutasArchivos): array
    {
        $apiKey = $this->asegurarApiKey();

        if (count($rutasArchivos) < 2 || count($rutasArchivos) > 4) {
            throw ValidationException::withMessages([
                'fotos' => 'Tripo requiere entre 2 y 4 imágenes de ángulos para procesar el modelado 3D.',
            ]);
        }

        try {
            $archivos = [];

            foreach ($rutasArchivos as $ruta) {
                $imageToken = $this->subirImagen($ruta);
                $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));

                $mimeType = match ($extension) {
                    'png' => 'png',
                    'webp' => 'webp',
                    default => 'jpeg',
                };

                $archivos[] = [
                    'type' => $mimeType,
                    'file_token' => $imageToken,
                ];
            }

            $payload = [
                'type' => 'multiview_to_model',
                'files' => $archivos,
            ];

            Log::info('Tripo [Task] Solicitud:', ['url' => $this->apiUrl.'/task', 'payload' => $payload]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl.'/task', $payload);

            if ($response->failed()) {
                Log::error('Tripo [Task] Falló: '.$response->body());
                throw new Exception('Error en Tripo API al iniciar modelado: '.$response->body());
            }

            $json = $response->json();

            if (isset($json['code']) && $json['code'] !== 0) {
                Log::error('Tripo [Task] Rechazado: '.json_encode($json));
                throw new Exception('Tripo rechazó la tarea: '.($json['message'] ?? json_encode($json)));
            }

            return $json;
        } catch (Exception $e) {
            Log::error('TripoService [generarMultiImageTo3d] Error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Consulta el estado de una tarea en Tripo API.
     *
     * @return array<string, mixed>
     */
    public function consultarEstadoTarea(string $taskId): array
    {
        $apiKey = $this->asegurarApiKey();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$apiKey,
            ])->get($this->apiUrl.'/task/'.$taskId);

            if ($response->failed()) {
                Log::error('Tripo [consultarEstado] Falló: '.$response->body());
                throw new Exception('Error al consultar estado en Tripo: '.$response->body());
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('TripoService [consultarEstadoTarea] Error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Descarga el archivo .glb desde la URL remota y lo guarda en storage/app/public/modelos-3d.
     * Retorna la ruta relativa en disco público (ej. 'modelos-3d/model_xyz.glb').
     */
    public function descargarGlb(string $urlRemota, string $taskId): string
    {
        $nombreArchivo = 'model_'.$taskId.'.glb';
        $rutaEnDisco = 'modelos-3d/'.$nombreArchivo;

        if (Storage::disk('public')->exists($rutaEnDisco)) {
            return $rutaEnDisco;
        }

        try {
            $respuesta = Http::timeout(60)->get($urlRemota);

            if ($respuesta->successful() && ! empty($respuesta->body())) {
                Storage::disk('public')->put($rutaEnDisco, $respuesta->body());

                return $rutaEnDisco;
            }

            $contenido = @file_get_contents($urlRemota);
            if ($contenido !== false && ! empty($contenido)) {
                Storage::disk('public')->put($rutaEnDisco, $contenido);

                return $rutaEnDisco;
            }

            return $urlRemota;
        } catch (Exception $e) {
            Log::error('TripoService [descargarGlb] Error al descargar .glb: '.$e->getMessage());

            return $urlRemota;
        }
    }
}
