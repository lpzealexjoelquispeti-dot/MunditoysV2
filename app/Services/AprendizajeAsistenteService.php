<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Ejecuta los modelos de machine learning del asistente que corren en Python (recomendador y pronóstico).
 * El clasificador de intención se evalúa en PHP (ClasificadorIntencionService).
 */
class AprendizajeAsistenteService
{
    /**
     * @param  array<string, mixed>  $solicitud
     * @return list<array{id: int, score: float, reasons: list<string>}>
     */
    public function recomendar(array $solicitud): array
    {
        $pesos = $this->metricas('recomendador_metricas')['pesos'] ?? null;

        return $this->ejecutar('recomendador.py', 'recomendar', $solicitud + ['weights' => $pesos])['items'] ?? [];
    }

    /**
     * @param  array<string, mixed>  $solicitud
     * @return array<string, mixed>
     */
    public function pronosticar(array $solicitud): array
    {
        return $this->ejecutar('pronostico.py', 'predecir', $solicitud + ['model' => $this->rutaModelo('pronostico')]);
    }

    /**
     * Informe de entrenamiento y evaluación de un modelo (para el panel de métricas).
     *
     * @return array<string, mixed>
     */
    public function metricas(string $clave): array
    {
        $ruta = config('asistente.modelos.directorio').DIRECTORY_SEPARATOR.config("asistente.modelos.{$clave}");

        return is_file($ruta) ? (json_decode((string) file_get_contents($ruta), true) ?? []) : [];
    }

    private function rutaModelo(string $clave): string
    {
        $ruta = config('asistente.modelos.directorio').DIRECTORY_SEPARATOR.config("asistente.modelos.{$clave}");
        if (! is_file($ruta)) {
            throw new RuntimeException('Falta el modelo entrenado '.basename($ruta).'. Ejecuta el entrenamiento del asistente.');
        }

        return $ruta;
    }

    /**
     * @param  array<string, mixed>  $entrada
     * @return array<string, mixed>
     */
    private function ejecutar(string $script, string $comando, array $entrada): array
    {
        $proceso = Process::timeout(config('asistente.timeout_python'))
            ->input(json_encode($entrada, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE))
            ->run([config('asistente.python'), '-B', app_path('Services/Asistente/'.$script), $comando]);

        $lineas = array_values(array_filter(explode("\n", trim($proceso->output()))));
        $resultado = $lineas === [] ? null : json_decode(end($lineas), true);
        if (! $proceso->successful() || ! is_array($resultado) || ($resultado['event'] ?? null) !== 'result') {
            throw new RuntimeException('El modelo de aprendizaje no respondió: '.($resultado['message'] ?? 'revisa el entorno Python del asistente.'));
        }

        return $resultado;
    }
}
