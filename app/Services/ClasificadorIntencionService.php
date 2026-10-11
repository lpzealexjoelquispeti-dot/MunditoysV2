<?php

namespace App\Services;

use Normalizer;
use RuntimeException;

/**
 * Clasificador de intención entrenado con scikit-learn (TF-IDF + SVM lineal calibrado) y evaluado en PHP.
 *
 * Lee intenciones_modelo.json, que exporta intenciones.py, y reproduce exactamente su predict_proba:
 * así cada mensaje se clasifica en milisegundos, sin arrancar Python.
 */
class ClasificadorIntencionService
{
    /** @var array<string, mixed>|null */
    private ?array $modelo = null;

    /**
     * @return array{intent: string, confidence: float, alternatives: list<array{intent: string, confidence: float}>}
     */
    public function clasificar(string $texto): array
    {
        $probabilidades = $this->probabilidades($texto);
        arsort($probabilidades);
        $orden = array_keys($probabilidades);

        return [
            'intent' => $orden[0],
            'confidence' => round($probabilidades[$orden[0]], 4),
            'alternatives' => array_map(
                fn (string $clase): array => ['intent' => $clase, 'confidence' => round($probabilidades[$clase], 4)],
                array_slice($orden, 1, 2),
            ),
        ];
    }

    /**
     * Probabilidad de cada intención.
     *
     * @return array<string, float>
     */
    public function probabilidades(string $texto): array
    {
        $modelo = $this->modelo();
        $normalizado = self::normalizar($texto);
        $rasgos = [];
        $desplazamiento = 0;
        foreach ($modelo['vectorizers'] as $vectorizador) {
            foreach ($this->tfidf($normalizado, $vectorizador) as $indice => $valor) {
                $rasgos[$desplazamiento + $indice] = $valor;
            }
            $desplazamiento += count($vectorizador['idf']);
        }

        $clases = $modelo['classes'];
        $total = array_fill_keys($clases, 0.0);
        foreach ($modelo['models'] as $lineal) {
            $decisiones = [];
            foreach ($lineal['classes'] as $fila => $clase) {
                $suma = $lineal['intercept'][$fila];
                foreach ($rasgos as $indice => $valor) {
                    $suma += $lineal['coef'][$fila][$indice] * $valor;
                }
                $decisiones[$clase] = $suma;
            }

            if ($modelo['tipo'] === 'logistica') {
                $maximo = max($decisiones);
                $exponenciales = array_map(fn (float $d): float => exp($d - $maximo), $decisiones);
                $suma = array_sum($exponenciales);
                foreach ($exponenciales as $clase => $valor) {
                    $total[$clase] += $valor / $suma;
                }

                continue;
            }

            // SVM calibrado (sigmoide de Platt por clase) y normalizado para que sume 1.
            $probabilidad = [];
            foreach ($lineal['classes'] as $fila => $clase) {
                [$a, $b] = $lineal['calibrators'][$fila];
                $probabilidad[$clase] = 1 / (1 + exp($a * $decisiones[$clase] + $b));
            }
            $suma = array_sum($probabilidad);
            foreach ($clases as $clase) {
                $total[$clase] += $suma > 0 ? ($probabilidad[$clase] ?? 0.0) / $suma : 1 / count($clases);
            }
        }

        return array_map(fn (float $p): float => $p / count($modelo['models']), $total);
    }

    public function umbral(): float
    {
        $ruta = config('asistente.modelos.directorio').DIRECTORY_SEPARATOR.config('asistente.modelos.intenciones_metricas');
        $metricas = is_file($ruta) ? json_decode((string) file_get_contents($ruta), true) : null;

        return (float) ($metricas['umbral_confianza'] ?? 0.3);
    }

    /**
     * Igual que normalizar() de intenciones.py: sin tildes, minúsculas y números agrupados.
     */
    public static function normalizar(string $texto): string
    {
        $texto = (string) Normalizer::normalize($texto, Normalizer::FORM_KD);
        $texto = strtolower((string) preg_replace('/[^\x00-\x7F]/', '', $texto));
        $texto = (string) preg_replace('/\d+/', ' 0 ', $texto);

        return trim((string) preg_replace('/\s+/', ' ', $texto));
    }

    /**
     * Términos como TfidfVectorizer: palabras de 2+ letras (1 a n seguidas) o n-gramas de letras por palabra (char_wb).
     *
     * @return list<string>
     */
    public static function terminos(string $texto, string $analizador, int $minimo, int $maximo): array
    {
        if ($analizador === 'word') {
            preg_match_all('/\b\w\w+\b/', $texto, $coincidencias);
            $palabras = $coincidencias[0];
            $terminos = [];
            for ($n = $minimo; $n <= $maximo; $n++) {
                for ($i = 0; $i + $n <= count($palabras); $i++) {
                    $terminos[] = implode(' ', array_slice($palabras, $i, $n));
                }
            }

            return $terminos;
        }

        $terminos = [];
        foreach (preg_split('/\s+/', (string) preg_replace('/\s\s+/', ' ', $texto), -1, PREG_SPLIT_NO_EMPTY) as $palabra) {
            $palabra = ' '.$palabra.' ';
            $largo = strlen($palabra);
            for ($n = $minimo; $n <= $maximo; $n++) {
                $inicio = 0;
                $terminos[] = substr($palabra, $inicio, $n);
                while ($inicio + $n < $largo) {
                    $inicio++;
                    $terminos[] = substr($palabra, $inicio, $n);
                }
                if ($inicio === 0) {
                    break;
                }
            }
        }

        return $terminos;
    }

    /**
     * Vector TF-IDF disperso (tf sublineal × idf, normalizado L2).
     *
     * @param  array<string, mixed>  $vectorizador
     * @return array<int, float>
     */
    private function tfidf(string $texto, array $vectorizador): array
    {
        [$minimo, $maximo] = $vectorizador['ngram_range'];
        $conteos = [];
        foreach (self::terminos($texto, $vectorizador['analyzer'], $minimo, $maximo) as $termino) {
            $indice = $vectorizador['vocabulary'][$termino] ?? null;
            if ($indice !== null) {
                $conteos[$indice] = ($conteos[$indice] ?? 0) + 1;
            }
        }

        $vector = [];
        foreach ($conteos as $indice => $conteo) {
            $vector[$indice] = (1 + log($conteo)) * $vectorizador['idf'][$indice];
        }
        $norma = sqrt(array_sum(array_map(fn (float $v): float => $v * $v, $vector)));

        return $norma > 0 ? array_map(fn (float $v): float => $v / $norma, $vector) : $vector;
    }

    /**
     * @return array<string, mixed>
     */
    private function modelo(): array
    {
        if ($this->modelo !== null) {
            return $this->modelo;
        }

        $ruta = config('asistente.modelos.directorio').DIRECTORY_SEPARATOR.config('asistente.modelos.intenciones_exportado');
        $modelo = is_file($ruta) ? json_decode((string) file_get_contents($ruta), true) : null;
        if (! is_array($modelo) || ! in_array($modelo['tipo'] ?? null, ['svm_calibrado', 'logistica'], true)) {
            throw new RuntimeException('Falta el clasificador de intención exportado. Ejecuta el entrenamiento del asistente.');
        }

        return $this->modelo = $modelo;
    }
}
