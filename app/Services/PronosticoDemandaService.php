<?php

namespace App\Services;

use App\Models\DetalleVenta;
use App\Models\LoteInventario;
use App\Models\Producto;
use App\Models\Venta;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Pronóstico de demanda por producto y sugerencia de reposición para el modo administrador.
 */
class PronosticoDemandaService
{
    public function __construct(private AprendizajeAsistenteService $aprendizaje) {}

    /**
     * @return array{
     *     origen: string, semanas: int, historia_simulada: bool, version_modelo: ?string,
     *     productos: list<array<string, mixed>>, categorias: list<array<string, mixed>>,
     *     semana_pico: ?array{semana: string, unidades: float}, presupuesto: ?array<string, mixed>
     * }
     */
    public function pronosticar(?int $semanas = null, ?float $presupuesto = null, ?int $categoriaId = null): array
    {
        $semanas = max(1, min($semanas ?? (int) config('asistente.pronostico.semanas'), 26));
        $origen = CarbonImmutable::now()->startOfWeek()->subWeek();
        $productos = Producto::query()->where('estado', 'activo')
            ->when($categoriaId, fn ($q) => $q->where('categoria_id', $categoriaId))
            ->with('categoria')->orderBy('id')->get();
        if ($productos->isEmpty()) {
            return ['origen' => $origen->toDateString(), 'semanas' => $semanas, 'historia_simulada' => false, 'version_modelo' => null,
                'productos' => [], 'categorias' => [], 'semana_pico' => null, 'presupuesto' => null];
        }

        $historia = $this->historiaSemanal($productos->pluck('id'), $origen);
        $resultado = $this->aprendizaje->pronosticar([
            'origin' => $origen->toDateString(),
            'weeks' => $semanas,
            'history_weeks' => (int) config('asistente.pronostico.semanas_historia'),
            'min_real_weeks' => (int) config('asistente.pronostico.minimo_semanas_reales'),
            'series' => $productos->map(fn (Producto $p): array => [
                'id' => $p->id, 'sales' => $historia[$p->id], 'price' => (float) $p->precio_venta,
            ])->values()->all(),
        ]);

        $stock = $this->stockPorProducto($productos->pluck('id'));
        $costos = $this->costoReferencia($productos);
        $seguridad = (float) config('asistente.pronostico.stock_seguridad');
        $series = collect($resultado['series'] ?? [])->keyBy('id');

        $filas = $productos->map(function (Producto $p) use ($series, $stock, $costos, $seguridad): array {
            $serie = $series->get($p->id, []);
            $demanda = (float) ($serie['total'] ?? 0);
            $disponible = $stock[$p->id] ?? 0;
            $reponer = max(0, (int) ceil($demanda * (1 + $seguridad) - $disponible));
            $semanal = $demanda / max(count($serie['forecast'] ?? []), 1);

            return [
                'producto_id' => $p->id,
                'producto' => $p->nombre,
                'categoria' => $p->categoria?->nombre,
                'demanda_estimada' => round($demanda, 1),
                'demanda_semanal' => round($semanal, 2),
                'stock_actual' => $disponible,
                'semanas_cobertura' => $semanal > 0 ? round($disponible / $semanal, 1) : null,
                'unidades_sugeridas' => $reponer,
                'costo_unitario' => $costos[$p->id],
                'costo_reposicion' => round($reponer * $costos[$p->id], 2),
                'semanas_reales' => (int) ($serie['real_weeks'] ?? 0),
                'semanas_simuladas' => (int) ($serie['simulated_weeks'] ?? 0),
                'detalle' => $serie['forecast'] ?? [],
            ];
        })->values();

        $porSemana = $filas->flatMap(fn (array $f) => $f['detalle'])->groupBy('week')
            ->map(fn (Collection $s, string $semana): array => ['semana' => $semana, 'unidades' => round($s->sum('units'), 1)]);

        return [
            'origen' => $origen->toDateString(),
            'semanas' => $semanas,
            'historia_simulada' => $filas->contains(fn (array $f): bool => $f['semanas_simuladas'] > 0),
            'historia_demostracion' => Venta::query()->where('numero_venta', 'like', 'DEMO-DIV-%')
                ->whereIn('estado', config('asistente.pronostico.estados_venta'))
                ->where('fecha_venta', '<', $origen->addWeek())->exists(),
            'version_modelo' => $resultado['model_version'] ?? null,
            'productos' => $filas->all(),
            'categorias' => $filas->groupBy('categoria')->map(fn (Collection $f, string $categoria): array => [
                'categoria' => $categoria,
                'demanda_estimada' => round($f->sum('demanda_estimada'), 1),
                'stock_actual' => $f->sum('stock_actual'),
                'unidades_sugeridas' => $f->sum('unidades_sugeridas'),
            ])->values()->all(),
            'semana_pico' => $porSemana->sortByDesc('unidades')->first(),
            'presupuesto' => $presupuesto !== null ? $this->repartirPresupuesto($filas, $presupuesto) : null,
        ];
    }

    /**
     * Reparte el presupuesto empezando por los productos que se agotarían antes.
     *
     * @param  Collection<int, array<string, mixed>>  $filas
     * @return array{disponible: float, usado: float, compras: list<array<string, mixed>>}
     */
    public function repartirPresupuesto(Collection $filas, float $presupuesto): array
    {
        $restante = $presupuesto;
        $compras = [];
        foreach ($filas->where('unidades_sugeridas', '>', 0)->sortBy(fn (array $f) => $f['semanas_cobertura'] ?? 0) as $fila) {
            $costo = (float) $fila['costo_unitario'];
            $unidades = $costo > 0 ? min($fila['unidades_sugeridas'], (int) floor($restante / $costo)) : 0;
            if ($unidades < 1) {
                continue;
            }
            $restante -= $unidades * $costo;
            $compras[] = ['producto' => $fila['producto'], 'unidades' => $unidades, 'costo' => round($unidades * $costo, 2),
                'cubre_sugerido' => $unidades === $fila['unidades_sugeridas']];
        }

        return ['disponible' => $presupuesto, 'usado' => round($presupuesto - $restante, 2), 'compras' => $compras];
    }

    /**
     * Unidades vendidas por semana (lunes a domingo) desde la primera venta de la tienda hasta el origen.
     *
     * @param  Collection<int, int>  $productoIds
     * @return array<int, list<float>>
     */
    private function historiaSemanal(Collection $productoIds, CarbonImmutable $origen): array
    {
        $ventas = DetalleVenta::query()
            ->join('ventas', 'ventas.id', '=', 'detalle_ventas.venta_id')
            ->whereIn('ventas.estado', config('asistente.pronostico.estados_venta'))
            ->whereIn('detalle_ventas.producto_id', $productoIds)
            ->where('ventas.fecha_venta', '<', $origen->addWeek())
            ->get(['detalle_ventas.producto_id', 'detalle_ventas.cantidad', 'ventas.fecha_venta']);

        $primera = $ventas->min('fecha_venta');
        $inicio = $primera ? CarbonImmutable::parse($primera)->startOfWeek() : $origen->addWeek();
        $total = $inicio > $origen ? 0 : (int) $inicio->diffInWeeks($origen) + 1;
        $historia = $productoIds->mapWithKeys(fn (int $id): array => [$id => array_fill(0, $total, 0.0)])->all();

        foreach ($ventas as $venta) {
            $indice = (int) $inicio->diffInWeeks(CarbonImmutable::parse($venta->fecha_venta)->startOfWeek());
            if ($indice >= 0 && $indice < $total) {
                $historia[$venta->producto_id][$indice] += (float) $venta->cantidad;
            }
        }

        return $historia;
    }

    /**
     * @param  Collection<int, int>  $productoIds
     * @return array<int, int>
     */
    private function stockPorProducto(Collection $productoIds): array
    {
        return LoteInventario::query()
            ->join('inventarios', 'inventarios.id', '=', 'lotes_inventario.inventario_id')
            ->whereNull('inventarios.deleted_at')
            ->where('lotes_inventario.estado', 'activo')
            ->where('lotes_inventario.cantidad_disponible', '>', 0)
            ->where(fn ($q) => $q->whereNull('lotes_inventario.fecha_vencimiento')->orWhereDate('lotes_inventario.fecha_vencimiento', '>=', today()))
            ->whereHas('inventario', fn ($q) => $q->whereHas('almacen', fn ($a) => $a->where('estado', true)->whereNull('almacenes.deleted_at')
                ->whereHas('sucursal', fn ($s) => $s->where('estado', true)->whereNull('sucursales.deleted_at'))))
            ->whereIn('inventarios.producto_id', $productoIds)
            ->groupBy('inventarios.producto_id')
            ->selectRaw('inventarios.producto_id, SUM(lotes_inventario.cantidad_disponible) as total')
            ->pluck('total', 'producto_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }

    /**
     * Costo del último lote ingresado o, si no hay lotes, el precio de compra de referencia.
     *
     * @param  Collection<int, Producto>  $productos
     * @return array<int, float>
     */
    private function costoReferencia(Collection $productos): array
    {
        $ultimos = LoteInventario::query()
            ->join('inventarios', 'inventarios.id', '=', 'lotes_inventario.inventario_id')
            ->whereIn('inventarios.producto_id', $productos->pluck('id'))
            ->orderBy('lotes_inventario.fecha_ingreso')->orderBy('lotes_inventario.id')
            ->get(['inventarios.producto_id', 'lotes_inventario.costo_unitario'])
            ->pluck('costo_unitario', 'producto_id');

        return $productos->mapWithKeys(fn (Producto $p): array => [
            $p->id => (float) ($ultimos[$p->id] ?? $p->precio_compra_referencia ?? 0),
        ])->all();
    }
}
