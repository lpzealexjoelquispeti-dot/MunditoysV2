<?php

return [
    'habilitado' => env('ASISTENTE_HABILITADO', true),
    'python' => env('ASISTENTE_PYTHON', storage_path('app/private/asistente/runtime/'.(PHP_OS_FAMILY === 'Windows' ? 'Scripts/python.exe' : 'bin/python'))),
    'timeout_python' => 60,
    'modelos' => [
        'directorio' => storage_path('app/private/asistente/modelos'),
        'intenciones_exportado' => 'intenciones_modelo.json',
        'intenciones_metricas' => 'intenciones_metricas.json',
        'pronostico' => 'pronostico_demanda.txt',
        'pronostico_metricas' => 'pronostico_metricas.json',
        'recomendador_metricas' => 'recomendador_metricas.json',
    ],
    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://127.0.0.1:11434'),
        'modelo_chat' => env('ASISTENTE_MODELO_CHAT', 'qwen3:8b'),
        'modelo_embeddings' => env('ASISTENTE_MODELO_EMBEDDINGS', 'bge-m3'),
        // bge-m3 en la CPU: si comparte la GPU con qwen3:8b, Ollama descarga uno para cargar el otro (≈10 s por consulta).
        'embeddings_en_cpu' => (bool) env('ASISTENTE_EMBEDDINGS_CPU', true),
        'timeout' => 180,
        // Con 8192 qwen3:8b ocupa 6,6 GB y un tercio pasa a la CPU (≈3 tokens/s); con 4096 rinde ≈12 tokens/s en 6 GB.
        'contexto' => (int) env('ASISTENTE_CONTEXTO', 4096),
        'mantener_cargado' => '30m',
        'temperatura' => 0.3,
    ],
    'conversacion' => [
        'mensajes_historial' => 10,
        'max_rondas_herramientas' => 4,
        'max_caracteres_mensaje' => 600,
        'mensajes_por_minuto' => 12,
        // Segundos que PHP deja vivir la petición de respuesta (el límite de 30 s corta el streaming de Ollama a medias).
        'tiempo_maximo_respuesta' => (int) env('ASISTENTE_TIEMPO_MAXIMO', 300),
    ],
    'busqueda' => [
        'resultados' => 5,
        'similitud_minima' => 0.35,
    ],
    'pronostico' => [
        'semanas' => 8,
        'semanas_historia' => 52,
        // Con menos semanas reales se completa la historia con una simulación calibrada (se informa en pantalla).
        'minimo_semanas_reales' => 12,
        'estados_venta' => ['completada', 'pagado'],
        'stock_seguridad' => 0.2,
    ],
];
