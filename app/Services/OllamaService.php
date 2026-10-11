<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Conecta la aplicación con el servidor local de Ollama.
 *
 * En tu proyecto utiliza:
 * - Qwen3:8b para conversar y solicitar herramientas.
 * - BGE-M3 para convertir textos en vectores de significado.
 *
 * Ejecuta inferencia con modelos previamente entrenados.
 * Este servicio no entrena el SVM ni reentrena los modelos del chat.
 */
class OllamaService
{
    /**
     * Envía un turno de conversación al modelo de lenguaje.
     *
     * $mensajes contiene el contexto proporcionado por el agente:
     * instrucciones, conversación y resultados de herramientas.
     *
     * $herramientas describe las funciones que el modelo puede solicitar.
     * Su ejecución y autorización corresponden a otros servicios.
     *
     * $pensar controla la opción de razonamiento de Ollama.
     *
     * $alEscribir permite mostrar progresivamente el texto recibido.
     *
     * @param  list<array<string, mixed>>  $mensajes
     * @param  list<array<string, mixed>>  $herramientas
     * @param  (callable(string): void)|null  $alEscribir
     * @return array{
     *     message: array<string, mixed>,
     *     prompt_tokens: int,
     *     output_tokens: int,
     *     duration_ms: int
     * }
     */
    public function conversar(
        array $mensajes,
        array $herramientas = [],
        bool $pensar = false,
        ?callable $alEscribir = null
    ): array {
        /**
         * Prepara los datos enviados al modelo.
         *
         * model: modelo de conversación configurado.
         * messages: contexto del turno.
         * stream: recibe la respuesta por fragmentos.
         * think: activa o desactiva la opción de razonamiento.
         * keep_alive: controla cuánto permanece cargado el modelo.
         *
         * num_ctx: capacidad de contexto configurada.
         * temperature: controla la variabilidad de la generación;
         * no representa un porcentaje de acierto.
         */
        $cuerpo = [
            'model' => config('asistente.ollama.modelo_chat'),
            'messages' => $mensajes,
            'stream' => true,
            'think' => $pensar,
            'keep_alive' => config('asistente.ollama.mantener_cargado'),
            'options' => [
                'num_ctx' => config('asistente.ollama.contexto'),
                'temperature' => config('asistente.ollama.temperatura'),
            ],
        ];

        /**
         * Entrega al modelo las herramientas disponibles en este turno.
         *
         * El modelo puede devolver una solicitud con nombre y argumentos.
         * Aquí no se ejecutan consultas ni operaciones de esas herramientas.
         */
        if ($herramientas !== []) {
            $cuerpo['tools'] = $herramientas;
        }

        /**
         * Envía la conversación al endpoint local /api/chat.
         *
         * El timeout limita la espera.
         * La opción HTTP stream permite leer el cuerpo progresivamente.
         */
        try {
            $respuesta = Http::timeout(config('asistente.ollama.timeout'))
                ->withOptions(['stream' => true])
                ->post(
                    config('asistente.ollama.url').'/api/chat',
                    $cuerpo
                );
        } catch (ConnectionException) {
            throw new RuntimeException(
                'El asistente no está disponible: Ollama no está corriendo en este equipo.'
            );
        }

        /**
         * Si la petición HTTP falla, informa el error recibido.
         */
        if (! $respuesta->successful()) {
            throw new RuntimeException(
                'Ollama respondió con un error: '.
                ($respuesta->json('error') ?? $respuesta->status())
            );
        }

        /**
         * Acumula:
         * - El texto generado.
         * - Las herramientas solicitadas.
         * - El fragmento final con estadísticas.
         *
         * $pendiente conserva datos que todavía no forman una línea completa.
         */
        $contenido = '';
        $llamadas = [];
        $final = [];
        $cuerpoRespuesta = $respuesta->toPsrResponse()->getBody();
        $pendiente = '';

        /**
         * Ollama transmite objetos JSON separados por saltos de línea.
         *
         * Lee bloques de hasta 2048 bytes. Un bloque puede contener
         * varias líneas o solo una parte de un objeto JSON.
         */
        while (! $cuerpoRespuesta->eof() || $pendiente !== '') {
            $pendiente .= $cuerpoRespuesta->eof()
                ? "\n"
                : $cuerpoRespuesta->read(2048);

            /**
             * Procesa únicamente las líneas completas disponibles.
             *
             * Al llegar al final del flujo, el salto añadido permite
             * procesar el último fragmento pendiente.
             */
            while (($salto = strpos($pendiente, "\n")) !== false) {
                $linea = trim(substr($pendiente, 0, $salto));
                $pendiente = substr($pendiente, $salto + 1);

                $parte = $linea === ''
                    ? null
                    : json_decode($linea, true);

                /**
                 * Omite líneas vacías o que no se decodifican como arreglo.
                 */
                if (! is_array($parte)) {
                    continue;
                }

                /**
                 * Detecta errores enviados durante la generación,
                 * incluso si la conexión HTTP inicial fue exitosa.
                 */
                if (isset($parte['error'])) {
                    throw new RuntimeException(
                        'Ollama respondió con un error: '.$parte['error']
                    );
                }

                /**
                 * Une los fragmentos de texto y guarda las solicitudes
                 * de herramientas que devuelve el modelo.
                 */
                $contenido .= (string) (
                    $parte['message']['content'] ?? ''
                );

                array_push(
                    $llamadas,
                    ...($parte['message']['tool_calls'] ?? [])
                );

                /**
                 * Envía el texto acumulado al callback mientras:
                 * - Existe un callback.
                 * - No se han registrado solicitudes de herramientas.
                 * - Hay contenido.
                 * - El fragmento no es el final.
                 */
                if (
                    $alEscribir
                    && $llamadas === []
                    && trim($contenido) !== ''
                    && ! ($parte['done'] ?? false)
                ) {
                    $alEscribir($contenido);
                }

                /**
                 * Conserva el objeto final, donde Ollama informa
                 * los tokens procesados y el tiempo de ejecución.
                 */
                if ($parte['done'] ?? false) {
                    $final = $parte;
                }
            }
        }

        /**
         * Evita devolver una respuesta completamente vacía.
         */
        if ($final === [] && $contenido === '' && $llamadas === []) {
            throw new RuntimeException(
                'El modelo de lenguaje devolvió una respuesta vacía.'
            );
        }

        /**
         * Devuelve el mensaje al servicio que coordina el agente.
         *
         * tool_calls contiene solicitudes pendientes de ejecución.
         * array_filter elimina los campos con valores vacíos.
         *
         * Las estadísticas miden consumo y duración,
         * no exactitud ni calidad de la respuesta.
         *
         * total_duration llega en nanosegundos y se convierte
         * a milisegundos dividiendo entre 1 000 000.
         */
        return [
            'message' => array_filter([
                'role' => 'assistant',
                'content' => $contenido,
                'tool_calls' => $llamadas,
            ]),
            'prompt_tokens' => (int) (
                $final['prompt_eval_count'] ?? 0
            ),
            'output_tokens' => (int) (
                $final['eval_count'] ?? 0
            ),
            'duration_ms' => (int) round(
                ($final['total_duration'] ?? 0) / 1_000_000
            ),
        ];
    }

    /**
     * Convierte textos en embeddings utilizando BGE-M3.
     *
     * Un embedding es un vector numérico que representa características
     * del significado de un texto.
     *
     * Otros servicios comparan estos vectores para recuperar productos
     * o información relacionados con la consulta del usuario.
     *
     * Aquí se generan los vectores; no se calcula su similitud.
     *
     * @param  list<string>  $textos
     * @return list<list<float>>
     */
    public function vectorizar(array $textos): array
    {
        /**
         * Evita realizar una petición si no hay textos.
         */
        if ($textos === []) {
            return [];
        }

        /**
         * Selecciona el modelo de embeddings.
         *
         * array_values convierte los textos en una lista
         * con índices consecutivos.
         */
        $cuerpo = [
            'model' => config('asistente.ollama.modelo_embeddings'),
            'input' => array_values($textos),
            'keep_alive' => config('asistente.ollama.mantener_cargado'),
        ];

        /**
         * Si está configurado, ejecuta los embeddings en CPU.
         *
         * num_gpu = 0 evita cargar capas del modelo en la GPU.
         */
        if (config('asistente.ollama.embeddings_en_cpu')) {
            $cuerpo['options'] = ['num_gpu' => 0];
        }

        /**
         * Solicita los vectores al endpoint de embeddings de Ollama.
         */
        $respuesta = $this->solicitar('/api/embed', $cuerpo);
        $vectores = $respuesta['embeddings'] ?? null;

        /**
         * Comprueba que la respuesta sea un arreglo y que exista
         * un vector por cada texto enviado.
         */
        if (
            ! is_array($vectores)
            || count($vectores) !== count($textos)
        ) {
            throw new RuntimeException(
                'El modelo de búsqueda no devolvió un vector por cada texto.'
            );
        }

        return $vectores;
    }

    /**
     * Comprueba si el servidor de Ollama responde.
     *
     * Esta comprobación no garantiza que un modelo específico
     * esté instalado o pueda generar una respuesta.
     */
    public function disponible(): bool
    {
        try {
            return Http::timeout(3)
                ->get(config('asistente.ollama.url').'/api/version')
                ->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    /**
     * Centraliza las peticiones POST que reciben una respuesta JSON.
     *
     * Se utiliza para obtener embeddings y evita repetir
     * la configuración HTTP y el manejo de errores.
     *
     * @param  array<string, mixed>  $cuerpo
     * @return array<string, mixed>
     */
    private function solicitar(string $ruta, array $cuerpo): array
    {
        try {
            $respuesta = Http::timeout(config('asistente.ollama.timeout'))
                ->acceptJson()
                ->post(
                    config('asistente.ollama.url').$ruta,
                    $cuerpo
                );
        } catch (ConnectionException) {
            throw new RuntimeException(
                'El asistente no está disponible: Ollama no está corriendo en este equipo.'
            );
        }

        /**
         * Informa errores HTTP devueltos por Ollama.
         */
        if (! $respuesta->successful()) {
            throw new RuntimeException(
                'Ollama respondió con un error: '.
                ($respuesta->json('error') ?? $respuesta->status())
            );
        }

        /**
         * Devuelve el JSON decodificado o un arreglo vacío
         * si no se obtiene contenido JSON.
         */
        return $respuesta->json() ?? [];
    }
}