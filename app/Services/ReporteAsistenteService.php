<?php

namespace App\Services;

use App\Models\DetalleVenta;
use App\Models\Entrega;
use App\Models\Inventario;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ReporteAsistenteService
{
    /** @return list<string> */
    public function seccionesPermitidas(?User $usuario): array
    {
        if (! $usuario?->can('acceder panel')) {
            return [];
        }

        return array_values(array_filter([
            $usuario->can('administrar accesos') || $usuario->can('crear ventas') ? 'ventas' : null,
            $usuario->can('administrar accesos') || $usuario->can('acceder logistica') ? 'logistica' : null,
            $usuario->can('gestionar inventario') ? 'inventario' : null,
        ]));
    }

    /** @return array<string, mixed>|null */
    public function solicitud(string $mensaje, ?User $usuario): ?array
    {
        $texto = Str::lower(Str::ascii($mensaje));
        $ultimas = (bool) preg_match('/\bultim[oa]s?\s+\d+\s+(ventas?|salidas?|entregas?|movimientos?)\b/', $texto);
        if (! $ultimas && ! preg_match('/\b(reportes?|informes?)\b/', $texto)) {
            return null;
        }
        $secciones = [];
        foreach (['ventas' => 'ventas?|vendimos|vendidos|ingresos|ticket', 'logistica' => 'logistica|entregas?|despachos?|cancelaciones', 'inventario' => 'inventario|stock|agotados|existencias'] as $seccion => $patron) {
            if (preg_match('/\b(?:'.$patron.')\b/', $texto)) {
                $secciones[] = $seccion;
            }
        }
        $detalle = null;
        $limite = null;
        if (preg_match('/\bultim[oa]s?\s+(\d+)\s+(ventas?|salidas?|entregas?|movimientos?)\b/', $texto, $coincidencia)) {
            $limite = (int) $coincidencia[1];
            $detalle = match (true) {
                str_starts_with($coincidencia[2], 'venta') => 'ventas',
                str_starts_with($coincidencia[2], 'entrega') => 'entregas',
                str_starts_with($coincidencia[2], 'movimiento') || in_array('inventario', $secciones, true) => 'movimientos',
                in_array('ventas', $secciones, true) => 'ventas',
                default => null,
            };
            if ($detalle === null) {
                return ['error' => '¿Con salidas te refieres a ventas o a salidas de inventario (mermas, muestras, exhibición)? Indica el tipo, el juguete y el periodo en la consulta.'];
            }
            $secciones = array_values(array_unique([...$secciones, match ($detalle) {
                'ventas' => 'ventas', 'entregas' => 'logistica', 'movimientos' => 'inventario',
            }]));
        }
        preg_match_all('/\b\d{4}-\d{2}-\d{2}\b/', $texto, $fechas);
        $desde = now()->subDays(29)->toDateString();
        $hasta = today()->toDateString();
        if (str_contains($texto, 'hoy')) {
            $desde = today()->toDateString();
        } elseif (str_contains($texto, 'ayer')) {
            $desde = $hasta = today()->subDay()->toDateString();
        } elseif (str_contains($texto, 'mes pasado')) {
            $desde = today()->subMonthNoOverflow()->startOfMonth()->toDateString();
            $hasta = today()->subMonthNoOverflow()->endOfMonth()->toDateString();
        } elseif (str_contains($texto, 'este mes')) {
            $desde = now()->startOfMonth()->toDateString();
        } elseif (preg_match('/ultimos\s+(\d+)\s+dias/', $texto, $dias)) {
            if ((int) $dias[1] < 1 || (int) $dias[1] > 366) {
                return ['error' => 'Consulta periodos de 1 a 366 días.'];
            }
            $desde = now()->subDays((int) $dias[1] - 1)->toDateString();
        } elseif ($fechas[0] === [] && preg_match('/\b(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre|semanas?|anos?)\b|\d{1,2}\/\d{1,2}/', $texto)) {
            return ['error' => 'Indica el periodo con dos fechas AAAA-MM-DD; también puedo consultar hoy, ayer, este mes, mes pasado o los últimos N días.'];
        }
        if (count($fechas[0]) > 2) {
            return ['error' => 'Indica únicamente la fecha inicial y la fecha final del reporte.'];
        }

        return ['secciones' => $secciones ?: $this->seccionesPermitidas($usuario), 'desde' => $fechas[0][0] ?? $desde,
            'hasta' => $fechas[0][1] ?? $hasta,
            ...($detalle ? ['detalle' => $detalle, 'limite' => $limite] : []),
            ...(($producto = $this->extraerProducto($mensaje)) !== null ? ['producto' => $producto] : []),
            'incluir_demostracion' => (bool) preg_match('/\b(demostracion|demo|ficticias|simulados)\b/', $texto)
                && ! preg_match('/\b(sin|excluir|excluye|no incluir)\b[\s\S]{0,40}\b(demostracion|demo|ficticias|simulados)\b/', $texto)];
    }

    /**
     * Devuelve indicadores y registros acotados sin datos personales. El solicitante no elige usuarios ni consultas.
     *
     * @param  array<string, mixed>  $argumentos
     * @return array<string, mixed>
     */
    public function generar(array $argumentos, ?User $usuario): array
    {
        $permitidas = $this->seccionesPermitidas($usuario);
        if ($permitidas === []) {
            return ['error' => 'Los reportes internos requieren una cuenta de personal autorizada.'];
        }
        $validador = Validator::make($argumentos, [
            'secciones' => 'required|array|min:1|max:3', 'secciones.*' => 'required|string|distinct|in:ventas,logistica,inventario',
            'desde' => 'required|date_format:Y-m-d|before_or_equal:hasta', 'hasta' => 'required|date_format:Y-m-d|before_or_equal:today',
            'incluir_demostracion' => 'sometimes|boolean',
            'producto' => 'sometimes|required|string|max:255',
            'detalle' => 'required_with:limite|string|in:ventas,movimientos,entregas',
            'limite' => 'required_with:detalle|integer|between:1,50',
        ]);
        if ($validador->fails()) {
            return ['error' => 'Revisa las secciones y las fechas del reporte: AAAA-MM-DD, inicio anterior al final, sin fechas futuras.'];
        }
        $argumentos = $validador->validated();
        if (array_diff($argumentos['secciones'], $permitidas) !== []) {
            return ['error' => 'Tu cuenta no tiene permiso para todas las secciones solicitadas. Puedo generar: '.implode(', ', $permitidas).'.'];
        }
        $seccionDetalle = match ($argumentos['detalle'] ?? null) {
            'ventas' => 'ventas', 'movimientos' => 'inventario', 'entregas' => 'logistica', default => null,
        };
        if ($seccionDetalle && (! in_array($seccionDetalle, $permitidas, true) || ! in_array($seccionDetalle, $argumentos['secciones'], true))) {
            return ['error' => 'El detalle solicitado requiere la sección autorizada de '.$seccionDetalle.'.'];
        }
        $producto = null;
        if (isset($argumentos['producto'])) {
            $encontrado = $this->resolverProducto($argumentos['producto']);
            if (isset($encontrado['error'])) {
                return $encontrado;
            }
            $producto = $encontrado['producto'];
            $argumentos['producto'] = $producto->codigo_producto;
        }
        $desde = CarbonImmutable::parse($argumentos['desde'])->startOfDay();
        $hasta = CarbonImmutable::parse($argumentos['hasta'])->endOfDay();
        if ($desde->diffInDays($hasta) > 366) {
            return ['error' => 'Consulta periodos de hasta 366 días.'];
        }
        $demo = (bool) ($argumentos['incluir_demostracion'] ?? false);
        $admin = $usuario->can('administrar accesos');
        $datos = [];
        $lineas = ['Reporte de MundiToys', 'Periodo: '.$desde->format('Y-m-d').' al '.$hasta->format('Y-m-d'),
            'Alcance: '.($admin ? 'administración general' : 'según los permisos de tu cuenta'),
            $demo ? 'Incluye registros de demostración. No representa resultados comerciales reales.' : 'Ventas y entregas: excluye registros marcados como demostración.'];
        if ($producto) {
            $lineas[] = 'Juguete: '.$producto->nombre.' ('.$producto->codigo_producto.')';
        }

        if (in_array('ventas', $argumentos['secciones'], true)) {
            $ventas = Venta::query()->whereBetween('fecha_venta', [$desde, $hasta])->whereIn('estado', ['pagada', 'completada']);
            if (! $admin) {
                $ventas->where('usuario_vendedor_id', $usuario->id)->where('tipo_venta', 'presencial');
            }
            $this->filtrarDemostracion($ventas, $demo);
            if ($producto) {
                $ventas->whereHas('detalles', fn (Builder $q) => $q->where('producto_id', $producto->id));
            }
            $resumen = (clone $ventas)->selectRaw('COUNT(*) as pedidos, COALESCE(SUM(total), 0) as ingresos')->first();
            $pedidos = (int) $resumen->pedidos;
            $ingresos = round((float) $resumen->ingresos, 2);
            if ($producto) {
                $ingresos = round((float) DetalleVenta::query()->where('producto_id', $producto->id)->whereIn('venta_id', (clone $ventas)->select('ventas.id'))->sum('subtotal'), 2);
            }
            $ticket = $pedidos > 0 ? round($ingresos / $pedidos, 2) : 0.0;
            $datos['ventas'] = ['pedidos' => $pedidos, 'ingresos_bs' => $ingresos, 'ticket_promedio_bs' => $ticket];
            $lineas = [...$lineas, '', 'Ventas'.($admin ? '' : ' propias de mostrador'), 'Pedidos pagados o completados: '.$pedidos,
                ($producto ? 'Importe del juguete (suma de subtotales, antes de descuentos del pedido y envío): Bs. ' : 'Ingresos registrados (suma del total de esos pedidos): Bs. ').number_format($ingresos, 2, '.', ''),
                ($producto ? 'Importe promedio del juguete por pedido: Bs. ' : 'Ticket promedio (ingresos / pedidos): Bs. ').number_format($ticket, 2, '.', ''),
                'Acción sugerida: revisar el periodo y los productos más vendidos antes de planificar promociones. Estos indicadores no calculan utilidad ni margen.'];
            if (($argumentos['detalle'] ?? null) === 'ventas') {
                $registros = (clone $ventas)->with(['detalles' => fn (Builder|Relation $q) => $q->when($producto, fn (Builder|Relation $d) => $d->where('producto_id', $producto->id))])
                    ->orderByDesc('fecha_venta')->orderByDesc('id')->limit($argumentos['limite'])->get()
                    ->map(fn (Venta $venta): array => ['numero' => $venta->numero_venta, 'fecha' => $venta->fecha_venta->format('Y-m-d H:i'),
                        'estado' => $venta->estado, 'cantidad_vendida' => (int) $venta->detalles->sum('cantidad'),
                        'importe_bs' => round((float) ($producto ? $venta->detalles->sum('subtotal') : $venta->total), 2)])->all();
                $datos['ultimas_ventas'] = $registros;
                $lineas[] = 'Últimas ventas: '.count($registros).' de '.$pedidos.' pedidos del periodo. Los indicadores anteriores abarcan todo el periodo, no solo esta lista.';
                foreach ($registros as $registro) {
                    $lineas[] = $registro['fecha'].' | '.$registro['numero'].' | '.$registro['estado'].' | Cantidad vendida: '.$registro['cantidad_vendida'].' | Importe: Bs. '.number_format($registro['importe_bs'], 2, '.', '');
                }
            }
        }
        if (in_array('logistica', $argumentos['secciones'], true)) {
            $entregas = Entrega::query()->whereDate('fecha_programada', '>=', $desde)->whereDate('fecha_programada', '<=', $hasta)
                ->whereHas('venta', function (Builder $venta) use ($demo): void {
                    $this->filtrarDemostracion($venta, $demo);
                });
            if (! $admin) {
                $entregas->where('empleado_id', $usuario->id);
            }
            if ($producto) {
                $entregas->whereHas('venta.detalles', fn (Builder $q) => $q->where('producto_id', $producto->id));
            }
            $estados = (clone $entregas)->selectRaw('estado, COUNT(*) as cantidad')->groupBy('estado')->pluck('cantidad', 'estado')->map(fn ($n): int => (int) $n)->all();
            $total = array_sum($estados);
            $completadas = $estados['entregado'] ?? 0;
            $tasa = $total > 0 ? round(100 * $completadas / $total, 2) : 0.0;
            $atrasadas = (clone $entregas)->whereIn('estado', ['pendiente', 'en_camino'])->whereDate('fecha_programada', '<', today())->count();
            $datos['logistica'] = ['total' => $total, 'estados' => $estados, 'atrasadas' => $atrasadas, 'porcentaje_entregado' => $tasa];
            $lineas = [...$lineas, '', 'Logística'.($admin ? ' general' : ' de tus entregas asignadas'), 'Entregas programadas: '.$total,
                'Entregadas: '.$completadas, 'Pendientes: '.($estados['pendiente'] ?? 0), 'En camino: '.($estados['en_camino'] ?? 0), 'Canceladas: '.($estados['cancelado'] ?? 0),
                'Sin completar con fecha anterior a hoy: '.$atrasadas,
                'Porcentaje entregado (entregadas / total programadas): '.$tasa.'%. Incluye pendientes; no mide puntualidad.',
                $atrasadas > 0 ? 'Acción sugerida: priorizar las entregas atrasadas desde el panel de logística.' : 'Acción sugerida: revisar pendientes y turnos antes del siguiente despacho.'];
            if (($argumentos['detalle'] ?? null) === 'entregas') {
                $registros = (clone $entregas)->with('venta:id,numero_venta')->orderByDesc('fecha_programada')->orderByDesc('hora_inicio')->orderByDesc('id')
                    ->limit($argumentos['limite'])->get()->map(fn (Entrega $entrega): array => ['numero' => $entrega->venta->numero_venta,
                        'fecha_programada' => $entrega->fecha_programada->toDateString(), 'estado' => $entrega->estado])->all();
                $datos['ultimas_entregas'] = $registros;
                $lineas[] = 'Últimas entregas por fecha programada: '.count($registros).' de '.$total.'.';
                foreach ($registros as $registro) {
                    $lineas[] = $registro['fecha_programada'].' | '.$registro['numero'].' | '.$registro['estado'];
                }
            }
        }
        if (in_array('inventario', $argumentos['secciones'], true)) {
            $inventarios = Inventario::query()->whereHas('producto', fn (Builder $q) => $q->where('estado', 'activo')->whereNull('productos.deleted_at'))
                ->when($producto, fn (Builder $q) => $q->where('producto_id', $producto->id))
                ->whereHas('almacen', fn (Builder $q) => $q->where('estado', true)->whereNull('almacenes.deleted_at')
                    ->whereHas('sucursal', fn (Builder $s) => $s->where('estado', true)->whereNull('sucursales.deleted_at')))
                ->withSum(['lotes as disponible' => fn (Builder $q) => $q->where('estado', 'activo')->where('cantidad_disponible', '>', 0)
                    ->where(fn (Builder $f) => $f->whereNull('fecha_vencimiento')->orWhereDate('fecha_vencimiento', '>=', today()))], 'cantidad_disponible')
                ->get();
            $agotados = $inventarios->filter(fn (Inventario $i): bool => (int) $i->disponible === 0)->count();
            $bajos = $inventarios->filter(fn (Inventario $i): bool => (int) $i->disponible > 0 && $i->disponible < $i->stock_minimo)->count();
            $datos['inventario'] = ['unidades_disponibles' => (int) $inventarios->sum('disponible'), 'registros_agotados' => $agotados, 'registros_bajo_minimo' => $bajos];
            $lineas = [...$lineas, '', 'Inventario actual (no histórico)', 'Unidades vendibles: '.$datos['inventario']['unidades_disponibles'],
                'Registros producto-almacén agotados: '.$agotados, 'Registros producto-almacén bajo mínimo: '.$bajos,
                'Solo suma lotes activos, no vencidos, en almacenes y sucursales activos. El inventario puede incluir existencias de demostración.',
                'Acción sugerida: revisar faltantes y solicitar un pronóstico antes de aprobar reposición.'];
        }
        if (($argumentos['detalle'] ?? null) === 'movimientos') {
            $movimientos = MovimientoInventario::query()->where('tipo_movimiento', 'salida')->whereBetween('created_at', [$desde, $hasta])
                ->whereHas('lote', fn (Builder $q) => $q->withTrashed()->when($producto, fn (Builder $l) => $l->whereHas('inventario', fn (Builder $i) => $i->where('producto_id', $producto->id))))
                ->when(! $demo, function (Builder $q): void {
                    $q->whereHas('lote', fn (Builder $l) => $l->withTrashed()->where('codigo_lote', 'not like', 'DEMO-DIV-%'))
                        ->where(fn (Builder $m) => $m->whereNull('referencia_tipo')->orWhere('referencia_tipo', '!=', 'venta')
                            ->orWhereIn('referencia_id', Venta::query()->select('id')->where(fn (Builder $v) => $v->whereNull('cotizacion_envio->es_demostracion')->orWhere('cotizacion_envio->es_demostracion', false))));
                });
            $total = (clone $movimientos)->count();
            $registros = $movimientos->with(['lote' => fn (Builder|Relation $q) => $q->withTrashed()->select('id', 'codigo_lote')])->orderByDesc('created_at')->orderByDesc('id')->limit($argumentos['limite'])->get()
                ->map(fn (MovimientoInventario $m): array => ['fecha' => $m->created_at->format('Y-m-d H:i'), 'lote' => $m->lote->codigo_lote,
                    'cantidad_salida' => (int) $m->cantidad, 'referencia_tipo' => $m->referencia_tipo === 'venta' ? 'venta' : 'ajuste de inventario'])->all();
            $datos['ultimas_salidas'] = $registros;
            $lineas[] = 'Últimas salidas de inventario: '.count($registros).' de '.$total.' registros por lote del periodo. Una venta puede originar varias salidas por FIFO; no se cuentan como pedidos distintos.';
            foreach ($registros as $registro) {
                $lineas[] = $registro['fecha'].' | Lote: '.$registro['lote'].' | Cantidad de salida: '.$registro['cantidad_salida'].' | '.$registro['referencia_tipo'];
            }
        }
        $lineas = [...$lineas, '', 'Los indicadores anteriores son cálculos de registros, no predicciones de ML. El asistente no cambia ventas, entregas ni compras por generar este reporte.'];

        return ['texto_reporte' => implode("\n\n", array_filter($lineas)), 'indicadores' => $datos, 'parametros_reporte' => $argumentos];
    }

    private function filtrarDemostracion(Builder $consulta, bool $incluir): void
    {
        if (! $incluir) {
            $consulta->where(fn (Builder $q) => $q->whereNull('cotizacion_envio->es_demostracion')->orWhere('cotizacion_envio->es_demostracion', false));
        }
    }

    private function extraerProducto(string $mensaje): ?string
    {
        if (preg_match('/["«“]([^"»”]{1,255})["»”]/u', $mensaje, $cita)) {
            return trim($cita[1]);
        }
        $texto = Str::lower(Str::ascii($mensaje));
        if (preg_match('/\b(?:del(?: juguete| producto)?|sobre el (?:juguete|producto)|para el (?:juguete|producto)|de(?:l)? (?:juguete|producto)|(?:ventas?|entregas?|salidas?) de)\s+(?!mes\b|inventario\b|stock\b|periodo\b|dia\b|ano\b|este mes\b|hoy\b|ayer\b|los ultimos\b)(\S.*?)(?=\s+(?:de este mes|este mes|del mes|de hoy|de ayer|de los ultimos|desde|hasta|en inventario|en ventas|sin demostracion|incluyendo|con demostracion)|$)/', $texto, $nombre)) {
            return trim($nombre[1], ' .?');
        }
        foreach (Producto::withTrashed()->get(['id', 'nombre', 'codigo_producto'])->sortByDesc(fn (Producto $p): int => strlen($p->nombre)) as $producto) {
            foreach ([$producto->codigo_producto, $producto->nombre] as $nombre) {
                if ($nombre && preg_match('/(?<![\pL\pN])'.preg_quote(Str::lower(Str::ascii($nombre)), '/').'(?![\pL\pN])/u', $texto)) {
                    return $producto->codigo_producto;
                }
            }
        }

        return null;
    }

    /** @return array{producto: Producto}|array{error: string} */
    private function resolverProducto(string $consulta): array
    {
        $texto = Str::lower(Str::ascii(trim($consulta)));
        $candidatos = [];
        $exactos = [];
        foreach (Producto::withTrashed()->select('id', 'nombre', 'codigo_producto')->cursor() as $producto) {
            $nombre = Str::lower(Str::ascii($producto->nombre));
            if ($texto === Str::lower(Str::ascii($producto->codigo_producto))) {
                return ['producto' => $producto];
            }
            if ($texto === $nombre) {
                $exactos[] = $producto;
            }
            if (str_contains($nombre, $texto)) {
                $candidatos[] = $producto;
            }
        }
        $candidatos = $exactos ?: $candidatos;
        if (count($candidatos) === 1) {
            return ['producto' => $candidatos[0]];
        }
        if ($candidatos !== []) {
            return ['error' => 'Encontré varios juguetes. Indica el código exacto: '.implode('; ', array_map(fn (Producto $p): string => $p->nombre.' ('.$p->codigo_producto.')', array_slice($candidatos, 0, 5))).'. Repite la consulta con el código.'];
        }

        return ['error' => 'No encontré ese juguete. Indica su nombre completo o código y repite la consulta; no generaré un reporte general en su lugar.'];
    }
}
