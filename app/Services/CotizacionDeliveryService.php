<?php

namespace App\Services;

use App\Models\ConfiguracionEnvio;
use App\Models\Direccion;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Http\Client\HttpClientException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class CotizacionDeliveryService
{
    public function __construct(private StockCheckoutService $stock, private UbicacionLaPazService $ubicacion) {}

    /** @return array<string, mixed> */
    public function cotizar(?User $usuario, ?int $direccionId, Collection $items): array
    {
        if (! $usuario) {
            throw ValidationException::withMessages(['direccionId' => 'Inicia sesión para elegir una de tus direcciones guardadas.']);
        }
        $direccion = Direccion::query()->where('user_id', $usuario->id)->find($direccionId);
        if (! $direccion || $direccion->latitud === null || $direccion->longitud === null
            || ! $this->ubicacion->contiene((float) $direccion->latitud, (float) $direccion->longitud)) {
            throw ValidationException::withMessages(['direccionId' => 'Selecciona una dirección propia con ubicación válida dentro del municipio de La Paz.']);
        }
        $config = ConfiguracionEnvio::query()->find(1);
        if (! $config?->habilitado || $config->tarifa_km_usd <= 0 || $config->minimo_usd <= 0 || $config->cambio_paralelo <= 0) {
            throw ValidationException::withMessages(['delivery' => 'El envío a domicilio aún no está habilitado. El administrador debe configurar sus tarifas.']);
        }
        $servidor = config('services.delivery.routing_url');
        if (! $servidor) {
            throw ValidationException::withMessages(['delivery' => 'El servicio de rutas no está configurado. No podemos calcular el envío todavía.']);
        }
        $sucursales = Sucursal::query()->where('estado', true)->whereNotNull('latitud')->whereNotNull('longitud')->get()
            ->filter(fn (Sucursal $sucursal) => $this->ubicacion->contiene((float) $sucursal->latitud, (float) $sucursal->longitud) && $this->stock->disponible($sucursal, $items));
        if ($sucursales->isEmpty()) {
            throw ValidationException::withMessages(['delivery' => 'Ninguna sucursal disponible puede preparar todos los juguetes y cantidades de tu carrito. Ajusta las cantidades; no dividiremos el pedido entre sucursales.']);
        }
        $opciones = [];
        foreach ($sucursales as $sucursal) {
            $coordenadas = "{$sucursal->longitud},{$sucursal->latitud};{$direccion->longitud},{$direccion->latitud}";
            try {
                $respuesta = Http::acceptJson()->connectTimeout(3)->timeout(8)
                    ->get(rtrim($servidor, '/').'/route/v1/driving/'.$coordenadas, [
                        'overview' => 'full', 'geometries' => 'geojson', 'alternatives' => 'false', 'radiuses' => '100;100',
                    ])->throw()->json();
            } catch (HttpClientException $e) {
                throw ValidationException::withMessages(['delivery' => 'No pudimos consultar las rutas. Intenta nuevamente; no se aplicará un envío gratuito ni una distancia aproximada.']);
            }
            if (($respuesta['code'] ?? '') === 'NoRoute' || ($respuesta['code'] ?? '') === 'NoSegment') {
                continue;
            }
            $ruta = $respuesta['routes'][0] ?? null;
            if (($respuesta['code'] ?? '') !== 'Ok' || ! is_numeric($ruta['distance'] ?? null)
                || ! is_finite((float) $ruta['distance']) || $ruta['distance'] < 0
                || ($ruta['geometry']['type'] ?? '') !== 'LineString' || count($ruta['geometry']['coordinates'] ?? []) < 2) {
                throw ValidationException::withMessages(['delivery' => 'El servicio de rutas devolvió una respuesta inválida. Vuelve a calcular el envío.']);
            }
            $opciones[] = ['sucursal' => $sucursal, 'ruta' => $ruta];
        }
        $opcion = collect($opciones)->sortBy('ruta.distance')->first();
        if (! $opcion) {
            throw ValidationException::withMessages(['direccionId' => 'No encontramos una ruta por calles hasta esta dirección. Revisa el pin de tu domicilio.']);
        }
        $km = round($opcion['ruta']['distance'] / 1000, 4);
        $importeUsd = max((float) $config->minimo_usd, $km * (float) $config->tarifa_km_usd);

        return [
            'sucursal_id' => $opcion['sucursal']->id,
            'direccion_id' => $direccion->id,
            'destino' => $direccion->only(['direccion_completa', 'zona', 'ciudad', 'latitud', 'longitud']),
            'origen' => $opcion['sucursal']->only(['nombre', 'direccion', 'latitud', 'longitud']),
            'distancia_km' => $km,
            'tarifa_km_usd' => (string) $config->tarifa_km_usd,
            'minimo_usd' => (string) $config->minimo_usd,
            'cambio_paralelo' => (string) $config->cambio_paralelo,
            'aplica_minimo' => $km * (float) $config->tarifa_km_usd <= (float) $config->minimo_usd,
            'costo_bs' => round($importeUsd * (float) $config->cambio_paralelo, 2),
            'ruta' => $opcion['ruta']['geometry'],
            'fecha' => today()->toDateString(),
            'carrito' => $items->map(fn (array $item) => [$item['producto_id'], $item['cantidad'], $item['precio_unitario']])->values()->all(),
        ];
    }
}
