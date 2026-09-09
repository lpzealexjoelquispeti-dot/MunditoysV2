<?php

namespace App\Services;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\ConversacionIA;
use App\Models\DetalleVenta;
use App\Models\InteraccionUsuario;
use App\Models\Inventario;
use App\Models\LoteInventario;
use App\Models\MensajeIA;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Resena;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReporteService
{
    /**
     * Devuelve las opciones para los filtros de los reportes.
     */
    public function obtenerOpcionesFiltros(): array
    {
        return [
            'sucursales' => Sucursal::orderBy('nombre')->get(['id', 'nombre', 'ciudad']),
            'categorias' => Categoria::orderBy('nombre')->get(['id', 'nombre']),
            'vendedores' => User::role(['Vendedor', 'Administrador'])->orderBy('name')->get(['id', 'name']),
            'metodos_pago' => [
                'efectivo' => 'Efectivo',
                'qr' => 'Transferencia QR',
                'tarjeta' => 'Tarjeta de Débito / Crédito',
                'stripe' => 'Pasarela Stripe',
                'transferencia' => 'Transferencia Bancaria',
            ],
            'tipos_interaccion' => [
                'vista' => 'Vistas de Producto',
                'ver_3d' => 'Exploración de Modelos 3D',
                'agregar_carrito' => 'Añadidos al Carrito',
                'busqueda' => 'Búsquedas en Catálogo',
                'clic_banner' => 'Clics Promocionales',
            ],
        ];
    }

    /**
     * Reporte 1: Ventas, Rentabilidad y Márgenes Reales (FIFO).
     */
    public function obtenerReporteVentas(array $filtros): array
    {
        $fechaDesde = ! empty($filtros['fecha_desde']) ? Carbon::parse($filtros['fecha_desde'])->startOfDay() : now()->startOfMonth();
        $fechaHasta = ! empty($filtros['fecha_hasta']) ? Carbon::parse($filtros['fecha_hasta'])->endOfDay() : now()->endOfDay();
        $sucursalId = $filtros['sucursal_id'] ?? null;
        $tipoVenta = $filtros['tipo_venta'] ?? null;
        $categoriaId = $filtros['categoria_id'] ?? null;
        $metodoPago = $filtros['metodo_pago'] ?? null;
        $vendedorId = $filtros['usuario_vendedor_id'] ?? null;

        $query = Venta::query()
            ->with(['cliente', 'vendedor', 'pago', 'entrega.sucursal', 'detalles.producto.categoria', 'detalles.lote'])
            ->whereBetween('fecha_venta', [$fechaDesde, $fechaHasta])
            ->where('estado', '!=', 'cancelada');

        if ($tipoVenta && $tipoVenta !== 'todos') {
            $query->where('tipo_venta', $tipoVenta);
        }

        if ($vendedorId && $vendedorId !== 'todos') {
            $query->where('usuario_vendedor_id', $vendedorId);
        }

        if ($metodoPago && $metodoPago !== 'todos') {
            $query->whereHas('pago', fn ($q) => $q->where('metodo_pago', $metodoPago));
        }

        if ($sucursalId && $sucursalId !== 'todos') {
            $query->where(function ($q) use ($sucursalId) {
                $q->whereHas('entrega', fn ($sq) => $sq->where('sucursal_origen_id', $sucursalId))
                    ->orWhereHas('detalles.lote.inventario.almacen', fn ($aq) => $aq->where('sucursal_id', $sucursalId));
            });
        }

        if ($categoriaId && $categoriaId !== 'todos') {
            $query->whereHas('detalles.producto', fn ($pq) => $pq->where('categoria_id', $categoriaId));
        }

        $ventas = $query->orderBy('fecha_venta', 'desc')->get();

        $totalIngresos = 0.0;
        $totalCosto = 0.0;
        $descuentos = 0.0;
        $unidadesVendidas = 0;
        $ventasPorDia = [];
        $ventasPorCanal = ['online' => 0.0, 'fisica' => 0.0];
        $ventasPorMetodo = [];
        $productosAgrupados = [];

        foreach ($ventas as $venta) {
            $subtotalVenta = (float) $venta->subtotal;
            $descuentoVenta = (float) $venta->descuento;
            $totalVenta = (float) $venta->total;

            $totalIngresos += $totalVenta;
            $descuentos += $descuentoVenta;

            $canal = strtolower($venta->tipo_venta) === 'fisica' ? 'fisica' : 'online';
            $ventasPorCanal[$canal] = ($ventasPorCanal[$canal] ?? 0) + $totalVenta;

            $metodo = $venta->pago ? ($venta->pago->metodo_pago ?: 'No especificado') : 'Pendiente';
            $ventasPorMetodo[$metodo] = ($ventasPorMetodo[$metodo] ?? 0) + $totalVenta;

            $diaClave = $venta->fecha_venta ? $venta->fecha_venta->format('d/m') : now()->format('d/m');
            if (! isset($ventasPorDia[$diaClave])) {
                $ventasPorDia[$diaClave] = ['fecha' => $diaClave, 'ingresos' => 0.0, 'ganancia' => 0.0, 'pedidos' => 0];
            }
            $ventasPorDia[$diaClave]['ingresos'] += $totalVenta;
            $ventasPorDia[$diaClave]['pedidos']++;

            $costoVenta = 0.0;
            foreach ($venta->detalles as $detalle) {
                if ($categoriaId && $categoriaId !== 'todos' && $detalle->producto?->categoria_id != $categoriaId) {
                    continue;
                }

                $cantidad = (int) $detalle->cantidad;
                $unidadesVendidas += $cantidad;
                $costoUnitario = $detalle->lote ? (float) $detalle->lote->costo_unitario : ($detalle->producto?->precio_compra_referencia ? (float) $detalle->producto->precio_compra_referencia : 0.0);

                $costoLinea = $costoUnitario * $cantidad;
                $ingresoLinea = (float) $detalle->subtotal;
                $costoVenta += $costoLinea;
                $totalCosto += $costoLinea;

                $prodId = $detalle->producto_id;
                $prodNombre = $detalle->producto ? $detalle->producto->nombre : 'Juguete #'.$prodId;
                $catNombre = $detalle->producto?->categoria ? $detalle->producto->categoria->nombre : 'Sin categoría';

                if (! isset($productosAgrupados[$prodId])) {
                    $productosAgrupados[$prodId] = [
                        'id' => $prodId,
                        'nombre' => $prodNombre,
                        'categoria' => $catNombre,
                        'unidades' => 0,
                        'ingresos' => 0.0,
                        'costos' => 0.0,
                        'ganancia' => 0.0,
                        'margen_pct' => 0.0,
                    ];
                }
                $productosAgrupados[$prodId]['unidades'] += $cantidad;
                $productosAgrupados[$prodId]['ingresos'] += $ingresoLinea;
                $productosAgrupados[$prodId]['costos'] += $costoLinea;
                $productosAgrupados[$prodId]['ganancia'] += ($ingresoLinea - $costoLinea);
            }

            $ventasPorDia[$diaClave]['ganancia'] += ($totalVenta - $costoVenta);
        }

        foreach ($productosAgrupados as $id => $p) {
            $productosAgrupados[$id]['margen_pct'] = $p['ingresos'] > 0
                ? round(($p['ganancia'] / $p['ingresos']) * 100, 1)
                : 0.0;
        }

        uasort($productosAgrupados, fn ($a, $b) => $b['ganancia'] <=> $a['ganancia']);
        $topProductos = array_slice($productosAgrupados, 0, 10);

        $gananciaBruta = $totalIngresos - $totalCosto;
        $margenPromedio = $totalIngresos > 0 ? round(($gananciaBruta / $totalIngresos) * 100, 1) : 0.0;
        $totalPedidos = $ventas->count();
        $ticketPromedio = $totalPedidos > 0 ? round($totalIngresos / $totalPedidos, 2) : 0.0;

        $serieCronologica = array_values(array_slice($ventasPorDia, -12, 12, true));

        return [
            'filtros_aplicados' => [
                'fecha_desde' => $fechaDesde->format('d/m/Y'),
                'fecha_hasta' => $fechaHasta->format('d/m/Y'),
                'sucursal' => $sucursalId ? Sucursal::find($sucursalId)?->nombre : 'Todas las sucursales',
                'tipo_venta' => $tipoVenta ?: 'Todos los canales',
                'categoria' => $categoriaId ? Categoria::find($categoriaId)?->nombre : 'Todas las categorías',
                'metodo_pago' => $metodoPago ?: 'Todos los métodos',
            ],
            'kpis' => [
                'total_ingresos' => round($totalIngresos, 2),
                'total_costo' => round($totalCosto, 2),
                'ganancia_bruta' => round($gananciaBruta, 2),
                'margen_promedio' => $margenPromedio,
                'total_pedidos' => $totalPedidos,
                'ticket_promedio' => $ticketPromedio,
                'unidades_vendidas' => $unidadesVendidas,
                'descuentos_aplicados' => round($descuentos, 2),
            ],
            'ventas_por_canal' => $ventasPorCanal,
            'ventas_por_metodo' => $ventasPorMetodo,
            'serie_cronologica' => $serieCronologica,
            'top_productos' => $topProductos,
            'ventas_registros' => $ventas->take(50),
        ];
    }

    /**
     * Reporte 2: Interacciones de Usuario, Modelos 3D y Topics del Asistente IA.
     */
    public function obtenerReporteInteracciones(array $filtros): array
    {
        $fechaDesde = ! empty($filtros['fecha_desde']) ? Carbon::parse($filtros['fecha_desde'])->startOfDay() : now()->subDays(30)->startOfDay();
        $fechaHasta = ! empty($filtros['fecha_hasta']) ? Carbon::parse($filtros['fecha_hasta'])->endOfDay() : now()->endOfDay();
        $tipoInteraccion = $filtros['tipo_interaccion'] ?? null;
        $categoriaId = $filtros['categoria_id'] ?? null;

        $interaccionesQuery = InteraccionUsuario::query()
            ->with(['producto.categoria', 'usuario'])
            ->whereBetween('created_at', [$fechaDesde, $fechaHasta]);

        if ($tipoInteraccion && $tipoInteraccion !== 'todos') {
            $interaccionesQuery->where('tipo_interaccion', $tipoInteraccion);
        }

        if ($categoriaId && $categoriaId !== 'todos') {
            $interaccionesQuery->whereHas('producto', fn ($q) => $q->where('categoria_id', $categoriaId));
        }

        $interacciones = $interaccionesQuery->latest()->get();

        $totalInteracciones = $interacciones->count();
        $conteoPorTipo = [];
        $productosInteraccion = [];
        $terminosBusqueda = [];
        $interaccionesPorDia = [];

        foreach ($interacciones as $it) {
            $tipo = $it->tipo_interaccion ?: 'general';
            $conteoPorTipo[$tipo] = ($conteoPorTipo[$tipo] ?? 0) + 1;

            $dia = $it->created_at ? $it->created_at->format('d/m') : now()->format('d/m');
            $interaccionesPorDia[$dia] = ($interaccionesPorDia[$dia] ?? 0) + 1;

            if ($it->producto_id && $it->producto) {
                $pid = $it->producto_id;
                if (! isset($productosInteraccion[$pid])) {
                    $productosInteraccion[$pid] = [
                        'id' => $pid,
                        'nombre' => $it->producto->nombre,
                        'categoria' => $it->producto->categoria?->nombre ?? 'General',
                        'vistas' => 0,
                        'interacciones_3d' => 0,
                        'carritos' => 0,
                        'total' => 0,
                    ];
                }
                $productosInteraccion[$pid]['total']++;
                if (in_array($tipo, ['vista', 'ver_producto', 'detalle'])) {
                    $productosInteraccion[$pid]['vistas']++;
                } elseif (in_array($tipo, ['ver_3d', 'interaccion_3d', 'modelo_3d'])) {
                    $productosInteraccion[$pid]['interacciones_3d']++;
                } elseif (in_array($tipo, ['agregar_carrito', 'carrito'])) {
                    $productosInteraccion[$pid]['carritos']++;
                }
            }

            if ($tipo === 'busqueda' || isset($it->datos_jsonb['termino']) || isset($it->datos_jsonb['query'])) {
                $termino = strtolower(trim($it->datos_jsonb['termino'] ?? ($it->datos_jsonb['query'] ?? '')));
                if (strlen($termino) > 2) {
                    $terminosBusqueda[$termino] = ($terminosBusqueda[$termino] ?? 0) + 1;
                }
            }
        }

        $conversacionesIaQuery = ConversacionIA::query()
            ->with(['mensajes'])
            ->whereBetween('created_at', [$fechaDesde, $fechaHasta]);

        $conversacionesIa = $conversacionesIaQuery->get();
        $totalConversacionesIa = $conversacionesIa->count();
        $totalMensajesIa = MensajeIA::whereBetween('created_at', [$fechaDesde, $fechaHasta])->count();

        $topicosIa = [];
        $palabrasClavePredefinidas = [
            'precio' => 'Consultas de Precios y Ofertas',
            'edad' => 'Juguetes por Rango de Edad',
            'bebé' => 'Juguetes de Primera Infancia / Bebé',
            'lego' => 'Bloques y Construcción',
            'robot' => 'Tecnología y Robótica Educativa',
            '3d' => 'Modelos y Vistas en 3D',
            'envio' => 'Tiempos de Entrega y Delivery',
            'stock' => 'Disponibilidad en Sucursales',
            'garantia' => 'Garantía y Devoluciones',
            'pago' => 'Métodos de Pago y Facturación',
        ];

        foreach ($conversacionesIa as $conv) {
            $textoAnalizar = strtolower(($conv->titulo ?? '').' '.collect($conv->mensajes)->pluck('mensaje')->implode(' '));
            foreach ($palabrasClavePredefinidas as $kw => $etiqueta) {
                if (str_contains($textoAnalizar, $kw)) {
                    $topicosIa[$etiqueta] = ($topicosIa[$etiqueta] ?? 0) + 1;
                }
            }
        }

        arsort($topicosIa);
        arsort($terminosBusqueda);
        uasort($productosInteraccion, fn ($a, $b) => $b['total'] <=> $a['total']);

        $totalVistas = $conteoPorTipo['vista'] ?? ($conteoPorTipo['ver_producto'] ?? 0);
        $total3D = $conteoPorTipo['ver_3d'] ?? ($conteoPorTipo['interaccion_3d'] ?? 0);
        $totalCarritos = $conteoPorTipo['agregar_carrito'] ?? ($conteoPorTipo['carrito'] ?? 0);
        $tasaConversion = $totalVistas > 0 ? round(($totalCarritos / $totalVistas) * 100, 1) : 0.0;

        return [
            'filtros_aplicados' => [
                'fecha_desde' => $fechaDesde->format('d/m/Y'),
                'fecha_hasta' => $fechaHasta->format('d/m/Y'),
                'tipo_interaccion' => $tipoInteraccion ?: 'Todos los eventos',
                'categoria' => $categoriaId ? Categoria::find($categoriaId)?->nombre : 'Todas las categorías',
            ],
            'kpis' => [
                'total_interacciones' => $totalInteracciones,
                'total_vistas_producto' => $totalVistas,
                'total_interacciones_3d' => $total3D,
                'total_agregados_carrito' => $totalCarritos,
                'total_conversaciones_ia' => $totalConversacionesIa,
                'total_mensajes_ia' => $totalMensajesIa,
                'tasa_conversion_visual' => $tasaConversion,
            ],
            'conteo_por_tipo' => $conteoPorTipo,
            'top_juguetes' => array_slice($productosInteraccion, 0, 10),
            'top_busquedas' => array_slice($terminosBusqueda, 0, 8, true),
            'topicos_ia' => array_slice($topicosIa, 0, 6, true),
            'serie_cronologica' => array_slice($interaccionesPorDia, -10, 10, true),
            'interacciones_registros' => $interacciones->take(50),
        ];
    }

    /**
     * Reporte 3: Inventario, Valoración de Capital y Rotación de Stock.
     */
    public function obtenerReporteInventario(array $filtros): array
    {
        $sucursalId = $filtros['sucursal_id'] ?? null;
        $categoriaId = $filtros['categoria_id'] ?? null;
        $estadoStock = $filtros['estado_stock'] ?? 'todos';

        $query = Inventario::query()
            ->with(['producto.categoria', 'almacen.sucursal', 'lotes'])
            ->whereNull('deleted_at');

        if ($sucursalId && $sucursalId !== 'todos') {
            $query->whereHas('almacen', fn ($q) => $q->where('sucursal_id', $sucursalId));
        }

        if ($categoriaId && $categoriaId !== 'todos') {
            $query->whereHas('producto', fn ($q) => $q->where('categoria_id', $categoriaId));
        }

        $inventarios = $query->get();

        $valorCostoTotal = 0.0;
        $valorVentaTotal = 0.0;
        $unidadesStockTotal = 0;
        $productosAgotados = 0;
        $productosBajoMinimo = 0;
        $stockPorCategoria = [];
        $lotesAlerta = [];
        $tablaInventario = [];

        foreach ($inventarios as $inv) {
            $stockDisponible = (int) $inv->lotes->where('estado', 'activo')->sum('cantidad_disponible');
            $stockMinimo = (int) $inv->stock_minimo;
            $unidadesStockTotal += $stockDisponible;

            $costoAcumulado = 0.0;
            $ventaAcumulada = 0.0;

            foreach ($inv->lotes as $lote) {
                if ($lote->estado === 'activo' && $lote->cantidad_disponible > 0) {
                    $cUnit = (float) $lote->costo_unitario;
                    $pUnit = (float) ($lote->precio_venta ?: $inv->producto?->precio_venta ?: 0);
                    $costoAcumulado += ($cUnit * $lote->cantidad_disponible);
                    $ventaAcumulada += ($pUnit * $lote->cantidad_disponible);

                    if ($lote->fecha_vencimiento && Carbon::parse($lote->fecha_vencimiento)->isPast()) {
                        $lotesAlerta[] = [
                            'lote' => $lote->codigo_lote,
                            'producto' => $inv->producto?->nombre ?? 'Juguete',
                            'sucursal' => $inv->almacen?->sucursal?->nombre ?? 'Principal',
                            'unidades' => $lote->cantidad_disponible,
                            'estado' => 'Vencido',
                            'fecha' => Carbon::parse($lote->fecha_vencimiento)->format('d/m/Y'),
                        ];
                    } elseif ($lote->fecha_vencimiento && Carbon::parse($lote->fecha_vencimiento)->diffInDays(now()) <= 60) {
                        $lotesAlerta[] = [
                            'lote' => $lote->codigo_lote,
                            'producto' => $inv->producto?->nombre ?? 'Juguete',
                            'sucursal' => $inv->almacen?->sucursal?->nombre ?? 'Principal',
                            'unidades' => $lote->cantidad_disponible,
                            'estado' => 'Por Vencer',
                            'fecha' => Carbon::parse($lote->fecha_vencimiento)->format('d/m/Y'),
                        ];
                    }
                }
            }

            $valorCostoTotal += $costoAcumulado;
            $valorVentaTotal += $ventaAcumulada;

            $condicion = 'Óptimo';
            if ($stockDisponible === 0) {
                $productosAgotados++;
                $condicion = 'Agotado';
            } elseif ($stockDisponible <= $stockMinimo) {
                $productosBajoMinimo++;
                $condicion = 'Bajo Mínimo';
            }

            if ($estadoStock === 'agotado' && $stockDisponible > 0) {
                continue;
            }
            if ($estadoStock === 'bajo_minimo' && ($stockDisponible > $stockMinimo || $stockDisponible === 0)) {
                continue;
            }
            if ($estadoStock === 'optimo' && ($stockDisponible <= $stockMinimo)) {
                continue;
            }

            $catNombre = $inv->producto?->categoria?->nombre ?? 'General';
            if (! isset($stockPorCategoria[$catNombre])) {
                $stockPorCategoria[$catNombre] = ['categoria' => $catNombre, 'unidades' => 0, 'valor_costo' => 0.0, 'valor_venta' => 0.0];
            }
            $stockPorCategoria[$catNombre]['unidades'] += $stockDisponible;
            $stockPorCategoria[$catNombre]['valor_costo'] += $costoAcumulado;
            $stockPorCategoria[$catNombre]['valor_venta'] += $ventaAcumulada;

            $tablaInventario[] = [
                'id' => $inv->id,
                'producto' => $inv->producto?->nombre ?? 'Producto',
                'codigo' => $inv->producto?->codigo_producto ?? 'N/A',
                'categoria' => $catNombre,
                'sucursal' => $inv->almacen?->sucursal?->nombre ?? 'Sucursal',
                'almacen' => $inv->almacen?->nombre ?? 'Almacén',
                'stock_actual' => $stockDisponible,
                'stock_minimo' => $stockMinimo,
                'condicion' => $condicion,
                'valor_costo' => round($costoAcumulado, 2),
                'valor_venta' => round($ventaAcumulada, 2),
                'margen_potencial' => round($ventaAcumulada - $costoAcumulado, 2),
            ];
        }

        $mermas = MovimientoInventario::query()
            ->with(['lote.inventario.producto'])
            ->whereIn('tipo_movimiento', ['salida_merma', 'salida_dañado', 'salida_exhibicion', 'ajuste_negativo'])
            ->latest()
            ->take(20)
            ->get();

        $totalUnidadesMerma = MovimientoInventario::whereIn('tipo_movimiento', ['salida_merma', 'salida_dañado', 'salida_exhibicion', 'ajuste_negativo'])->sum('cantidad');

        $margenPotencialTotal = $valorVentaTotal - $valorCostoTotal;
        $margenPorcentajeTotal = $valorVentaTotal > 0 ? round(($margenPotencialTotal / $valorVentaTotal) * 100, 1) : 0.0;

        return [
            'filtros_aplicados' => [
                'sucursal' => $sucursalId ? Sucursal::find($sucursalId)?->nombre : 'Todas las sucursales',
                'categoria' => $categoriaId ? Categoria::find($categoriaId)?->nombre : 'Todas las categorías',
                'estado_stock' => ucfirst($estadoStock),
            ],
            'kpis' => [
                'valor_costo_total' => round($valorCostoTotal, 2),
                'valor_venta_total' => round($valorVentaTotal, 2),
                'margen_potencial' => round($margenPotencialTotal, 2),
                'margen_porcentaje' => $margenPorcentajeTotal,
                'unidades_stock_total' => $unidadesStockTotal,
                'productos_agotados' => $productosAgotados,
                'productos_bajo_minimo' => $productosBajoMinimo,
                'unidades_merma' => (int) $totalUnidadesMerma,
            ],
            'stock_por_categoria' => array_values($stockPorCategoria),
            'lotes_alerta' => array_slice($lotesAlerta, 0, 10),
            'mermas_recientes' => $mermas,
            'inventario_registros' => array_slice($tablaInventario, 0, 50),
        ];
    }

    /**
     * Reporte 4: Rendimiento Comercial, Sucursales, Vendedores y Entregas.
     */
    public function obtenerReporteRendimientoComercial(array $filtros): array
    {
        $fechaDesde = ! empty($filtros['fecha_desde']) ? Carbon::parse($filtros['fecha_desde'])->startOfDay() : now()->startOfMonth();
        $fechaHasta = ! empty($filtros['fecha_hasta']) ? Carbon::parse($filtros['fecha_hasta'])->endOfDay() : now()->endOfDay();
        $sucursalId = $filtros['sucursal_id'] ?? null;
        $vendedorId = $filtros['vendedor_id'] ?? null;

        $sucursales = Sucursal::with(['almacenes.inventarios.lotes'])->get();
        $rendimientoSucursales = [];

        foreach ($sucursales as $suc) {
            $ventasSucursalQuery = Venta::query()
                ->whereBetween('fecha_venta', [$fechaDesde, $fechaHasta])
                ->where('estado', '!=', 'cancelada')
                ->where(function ($q) use ($suc) {
                    $q->whereHas('entrega', fn ($eq) => $eq->where('sucursal_origen_id', $suc->id))
                        ->orWhereHas('detalles.lote.inventario.almacen', fn ($aq) => $aq->where('sucursal_id', $suc->id));
                });

            $totalFacturado = (float) $ventasSucursalQuery->sum('total');
            $totalPedidos = (int) $ventasSucursalQuery->count();

            $rendimientoSucursales[$suc->id] = [
                'id' => $suc->id,
                'nombre' => $suc->nombre,
                'ciudad' => $suc->ciudad,
                'facturacion' => round($totalFacturado, 2),
                'pedidos' => $totalPedidos,
                'ticket_promedio' => $totalPedidos > 0 ? round($totalFacturado / $totalPedidos, 2) : 0.0,
            ];
        }

        uasort($rendimientoSucursales, fn ($a, $b) => $b['facturacion'] <=> $a['facturacion']);

        $vendedores = User::role(['Vendedor', 'Administrador'])->get();
        $rankingVendedores = [];

        foreach ($vendedores as $vend) {
            if ($vendedorId && $vendedorId !== 'todos' && $vend->id != $vendedorId) {
                continue;
            }

            $ventasVendQuery = Venta::query()
                ->where('usuario_vendedor_id', $vend->id)
                ->whereBetween('fecha_venta', [$fechaDesde, $fechaHasta])
                ->where('estado', '!=', 'cancelada');

            $totalVendido = (float) $ventasVendQuery->sum('total');
            $cantVentas = (int) $ventasVendQuery->count();

            if ($cantVentas > 0 || ($vendedorId && $vend->id == $vendedorId)) {
                $rankingVendedores[] = [
                    'id' => $vend->id,
                    'nombre' => $vend->name,
                    'email' => $vend->email,
                    'ventas_conteo' => $cantVentas,
                    'total_vendido' => round($totalVendido, 2),
                    'ticket_promedio' => $cantVentas > 0 ? round($totalVendido / $cantVentas, 2) : 0.0,
                ];
            }
        }

        usort($rankingVendedores, fn ($a, $b) => $b['total_vendido'] <=> $a['total_vendido']);

        $entregasQuery = DB::table('entregas')
            ->whereBetween('created_at', [$fechaDesde, $fechaHasta]);

        if ($sucursalId && $sucursalId !== 'todos') {
            $entregasQuery->where('sucursal_origen_id', $sucursalId);
        }

        $entregas = $entregasQuery->get();
        $totalEntregas = $entregas->count();
        $entregasCompletadas = $entregas->where('estado', 'entregado')->count();
        $entregasEnCamino = $entregas->whereIn('estado', ['en_camino', 'en_ruta'])->count();
        $entregasPendientes = $entregas->whereIn('estado', ['pendiente', 'asignado'])->count();
        $entregasFallidas = $entregas->whereIn('estado', ['fallido', 'cancelado'])->count();

        $tasaExitoEntrega = $totalEntregas > 0 ? round(($entregasCompletadas / $totalEntregas) * 100, 1) : 100.0;

        $resenasQuery = Resena::query()
            ->whereBetween('created_at', [$fechaDesde, $fechaHasta]);

        $totalResenas = $resenasQuery->count();
        $calificacionPromedio = $totalResenas > 0 ? round((float) $resenasQuery->avg('calificacion'), 1) : 5.0;

        $distribucionEstrellas = [
            5 => Resena::whereBetween('created_at', [$fechaDesde, $fechaHasta])->where('calificacion', 5)->count(),
            4 => Resena::whereBetween('created_at', [$fechaDesde, $fechaHasta])->where('calificacion', 4)->count(),
            3 => Resena::whereBetween('created_at', [$fechaDesde, $fechaHasta])->where('calificacion', 3)->count(),
            2 => Resena::whereBetween('created_at', [$fechaDesde, $fechaHasta])->where('calificacion', 2)->count(),
            1 => Resena::whereBetween('created_at', [$fechaDesde, $fechaHasta])->where('calificacion', 1)->count(),
        ];

        $sucursalTop = ! empty($rendimientoSucursales) ? reset($rendimientoSucursales)['nombre'] : 'N/A';
        $vendedorTop = ! empty($rankingVendedores) ? $rankingVendedores[0]['nombre'] : 'N/A';

        return [
            'filtros_aplicados' => [
                'fecha_desde' => $fechaDesde->format('d/m/Y'),
                'fecha_hasta' => $fechaHasta->format('d/m/Y'),
                'sucursal' => $sucursalId ? Sucursal::find($sucursalId)?->nombre : 'Todas las sucursales',
                'vendedor' => $vendedorId ? User::find($vendedorId)?->name : 'Todos los colaboradores',
            ],
            'kpis' => [
                'sucursal_top' => $sucursalTop,
                'vendedor_top' => $vendedorTop,
                'total_entregas' => $totalEntregas,
                'tasa_exito_entrega' => $tasaExitoEntrega,
                'calificacion_promedio' => $calificacionPromedio,
                'total_resenas' => $totalResenas,
            ],
            'rendimiento_sucursales' => array_values($rendimientoSucursales),
            'ranking_vendedores' => $rankingVendedores,
            'resumen_entregas' => [
                'completadas' => $entregasCompletadas,
                'en_camino' => $entregasEnCamino,
                'pendientes' => $entregasPendientes,
                'fallidas' => $entregasFallidas,
            ],
            'distribucion_estrellas' => $distribucionEstrellas,
        ];
    }
}

