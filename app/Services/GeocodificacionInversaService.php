<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Traduce coordenadas del mapa a calle y zona usando Nominatim (OpenStreetMap).
 * Los resultados se guardan en caché para no repetir consultas del mismo punto.
 */
class GeocodificacionInversaService
{
    /**
     * @return array{direccion: ?string, zona: ?string}|null null si el servicio no respondió
     */
    public function buscar(float $latitud, float $longitud): ?array
    {
        $clave = sprintf('geocodificacion-inversa:%.5f,%.5f', $latitud, $longitud);

        if (Cache::has($clave)) {
            return Cache::get($clave);
        }

        try {
            $respuesta = Http::acceptJson()->connectTimeout(3)->timeout(6)
                ->withUserAgent(config('services.geocodificacion.user_agent'))
                ->get(rtrim(config('services.geocodificacion.url'), '/').'/reverse', [
                    'format' => 'jsonv2',
                    'lat' => $latitud,
                    'lon' => $longitud,
                    'zoom' => 18,
                    'addressdetails' => 1,
                    'accept-language' => 'es',
                ])->throw()->json();
        } catch (ConnectionException|RequestException) {
            return null;
        }

        $direccion = $respuesta['address'] ?? null;
        if (! is_array($direccion)) {
            return null;
        }

        $calle = $direccion['road'] ?? $direccion['pedestrian'] ?? $direccion['footway'] ?? null;
        $resultado = [
            'direccion' => $calle ? trim($calle.(isset($direccion['house_number']) ? ' #'.$direccion['house_number'] : '')) : null,
            // Del barrio más específico al macrodistrito: en La Paz OSM no siempre registra el barrio.
            'zona' => $direccion['neighbourhood'] ?? $direccion['residential'] ?? $direccion['quarter']
                ?? $direccion['suburb'] ?? $direccion['city_district'] ?? $direccion['district'] ?? null,
        ];

        Cache::put($clave, $resultado, now()->addDays(30));

        return $resultado;
    }
}
