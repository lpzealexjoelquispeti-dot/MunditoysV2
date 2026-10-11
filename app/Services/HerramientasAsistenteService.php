<?php

namespace App\Services;

use App\Models\BaseConocimientoIA;
use App\Models\Carrito;
use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\LoteInventario;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * Herramientas que el modelo de lenguaje puede pedir. El modelo nunca consulta la base de datos
 * directamente: solo elige una herramienta y sus argumentos; aquí se validan y se aplican los permisos.
 */
class HerramientasAsistenteService
{
    public function __construct(
        private BusquedaSemanticaService $busqueda,
        private AprendizajeAsistenteService $aprendizaje,
        private PronosticoDemandaService $pronostico,
        private StockCheckoutService $stock,
        private ReporteAsistenteService $reportes,
    ) {}

    /**
     * Definiciones en el formato de herramientas de Ollama, según lo que el usuario puede hacer.
     *
     * @return list<array<string, mixed>>
     */
    public function definiciones(?User $usuario): array
    {
        $herramientas = [
            $this->definir('buscar_productos', 'Busca juguetes del catálogo por tema, personaje o tipo. Devuelve precio, edad, medidas y disponibilidad; las cantidades son privadas.', [
                'consulta' => ['type' => 'string', 'description' => 'Lo que busca, en palabras del cliente'],
                'edad' => ['type' => 'integer', 'description' => 'Edad en años solo si el cliente la dijo para esta búsqueda; nunca la supongas'],
                'precio_max' => ['type' => 'number', 'description' => 'Presupuesto en bolivianos solo si el cliente lo dijo para esta búsqueda; no reutilices el de otro regalo'],
            ], ['consulta']),
            $this->definir('recomendar_regalo', 'Recomienda juguetes con el modelo de machine learning de la tienda según edad, presupuesto y gustos. Solo devuelve productos con stock.', [
                'edad' => ['type' => 'integer', 'description' => 'Edad en años de quien recibe este regalo, solo si el cliente la dijo; nunca la supongas'],
                'presupuesto' => ['type' => 'number', 'description' => 'Monto máximo en bolivianos solo si el cliente lo dijo para este regalo; no reutilices el de otro regalo'],
                'gustos' => ['type' => 'string', 'description' => 'Temas, personajes o intereses (ej. Marvel, superhéroes, coleccionar)'],
            ]),
            $this->definir('disponibilidad_producto', 'Disponibilidad de un producto en cada sucursal. Solo el personal con permiso de inventario recibe cantidades.', [
                'producto' => ['type' => 'string', 'description' => 'Nombre del producto'],
            ], ['producto']),
            $this->definir('estado_pedido', 'Estado de los pedidos del cliente que inició sesión (pago, preparación, entrega).', [
                'numero_venta' => ['type' => 'string', 'description' => 'Número del pedido, si lo indicó'],
            ]),
            $this->definir('informacion_tienda', 'Sucursales, horarios, envíos, pagos, devoluciones y demás información de la tienda.', [
                'pregunta' => ['type' => 'string', 'description' => 'La duda del cliente'],
            ], ['pregunta']),
        ];

        if ($this->puedeVerInventario($usuario)) {
            $herramientas[] = $this->definir('pronosticar_demanda', 'Pronóstico de demanda por producto (machine learning con temporada boliviana) y sugerencia de reposición; con presupuesto, reparte la compra.', [
                'semanas' => ['type' => 'integer', 'description' => 'Semanas a pronosticar (1 a 26). Para diciembre desde septiembre, unas 14.'],
                'presupuesto' => ['type' => 'number', 'description' => 'Presupuesto de compra en bolivianos, si lo dijo'],
            ]);
            $herramientas[] = $this->definir('revisar_inventario', 'Productos agotados, bajo el stock mínimo o con lotes por vencer.', []);
            $herramientas[] = $this->definir('analizar_rotacion', 'Analiza ventas de los últimos 90 días, unidades disponibles y productos sin ventas para priorizar revisión comercial. No modifica precios ni inventario.', []);
        }
        if ($usuario?->can('administrar accesos')) {
            $herramientas[] = $this->definir('resumen_ventas', 'Ventas reales de la tienda en un periodo: total, unidades y productos más vendidos.', [
                'desde' => ['type' => 'string', 'description' => 'Fecha inicial AAAA-MM-DD (por defecto, hace 30 días)'],
                'hasta' => ['type' => 'string', 'description' => 'Fecha final AAAA-MM-DD (por defecto, hoy)'],
            ]);
        }

        $secciones = $this->reportes->seccionesPermitidas($usuario);
        if ($secciones !== []) {
            $herramientas[] = $this->definir('generar_reporte', 'Genera un reporte explicado con datos verificados y alcance por cuenta. Secciones permitidas: '.implode(', ', $secciones).'. Ventas propias para vendedores, entregas asignadas para logística, resumen general para administración. Excluye demostración salvo petición explícita.', [
                'secciones' => ['type' => 'array', 'items' => ['type' => 'string', 'enum' => $secciones]],
                'desde' => ['type' => 'string', 'description' => 'Fecha inicial AAAA-MM-DD'],
                'hasta' => ['type' => 'string', 'description' => 'Fecha final AAAA-MM-DD'],
                'incluir_demostracion' => ['type' => 'boolean', 'description' => 'Solo true si pidió datos de demostración'],
                'producto' => ['type' => 'string', 'description' => 'Nombre o código del juguete específico que solicitó; si coincide con varios pide aclaración'],
                'detalle' => ['type' => 'string', 'enum' => ['ventas', 'movimientos', 'entregas'], 'description' => 'Tipo de registros recientes. Salidas es ambiguo: aclarar ventas o movimientos de inventario'],
                'limite' => ['type' => 'integer', 'description' => 'Número de registros pedidos: de 1 a 50. Requerido junto con detalle'],
            ], ['secciones', 'desde', 'hasta']);
        }

        return $herramientas;
    }

    /**
     * @param  array<string, mixed>  $argumentos
     * @return array<string, mixed>
     */
    public function ejecutar(string $nombre, array $argumentos, ?User $usuario): array
    {
        $permitidas = collect($this->definiciones($usuario))->pluck('function.name');
        if (! $permitidas->contains($nombre)) {
            return ['error' => 'Herramienta no disponible para este usuario.'];
        }
        if ($nombre === 'generar_reporte') {
            return $this->reportes->generar($argumentos, $usuario);
        }
        $reglas = match ($nombre) {
            'buscar_productos' => ['consulta' => 'required|string|max:600', 'edad' => 'nullable|integer|between:0,99', 'precio_max' => 'nullable|numeric|min:0|max:1000000'],
            'recomendar_regalo' => ['edad' => 'nullable|integer|between:0,99', 'presupuesto' => 'nullable|numeric|min:0|max:1000000', 'gustos' => 'nullable|string|max:600'],
            'disponibilidad_producto' => ['producto' => 'required|string|max:255'],
            'estado_pedido' => ['numero_venta' => 'nullable|string|max:100'],
            'informacion_tienda' => ['pregunta' => 'required|string|max:600'],
            'pronosticar_demanda' => ['semanas' => 'nullable|integer|between:1,26', 'presupuesto' => 'nullable|numeric|min:0|max:1000000'],
            'resumen_ventas' => ['desde' => 'nullable|date_format:Y-m-d', 'hasta' => 'nullable|date_format:Y-m-d'],
            default => [],
        };
        $validacion = Validator::make($argumentos, $reglas);
        if ($validacion->fails()) {
            return ['error' => 'Los criterios de consulta no son válidos. Revisa edad, presupuesto, fechas o nombre del producto.'];
        }
        $argumentos = $validacion->validated();

        try {
            $resultado = match ($nombre) {
                'buscar_productos' => $this->buscarProductos((string) ($argumentos['consulta'] ?? ''), $this->entero($argumentos['edad'] ?? null), $this->numero($argumentos['precio_max'] ?? null)),
                'recomendar_regalo' => $this->recomendarRegalo($this->entero($argumentos['edad'] ?? null), $this->numero($argumentos['presupuesto'] ?? null), (string) ($argumentos['gustos'] ?? ''), $usuario),
                'disponibilidad_producto' => $this->disponibilidad((string) ($argumentos['producto'] ?? '')),
                'estado_pedido' => $this->estadoPedido($usuario, $argumentos['numero_venta'] ?? null),
                'informacion_tienda' => $this->informacionTienda((string) ($argumentos['pregunta'] ?? '')),
                'pronosticar_demanda' => $this->resumirPronostico($this->pronostico->pronosticar($this->entero($argumentos['semanas'] ?? null), $this->numero($argumentos['presupuesto'] ?? null))),
                'revisar_inventario' => $this->revisarInventario(),
                'analizar_rotacion' => $this->analizarRotacion(),
                'resumen_ventas' => $this->resumenVentas($argumentos['desde'] ?? null, $argumentos['hasta'] ?? null),
            };

            return $this->puedeVerInventario($usuario) ? $resultado : $this->disponibilidadPublica($resultado);
        } catch (Throwable $error) {
            report($error);

            return ['error' => 'No se pudo consultar esa información en este momento.'];
        }
    }

    public function puedeVerInventario(?User $usuario): bool
    {
        return $usuario !== null && $usuario->can('acceder panel') && $usuario->can('gestionar inventario');
    }

    /** @param array<string|int, mixed> $datos
     * @return array<string|int, mixed>
     */
    private function disponibilidadPublica(array $datos): array
    {
        foreach ($datos as $clave => $valor) {
            if (in_array($clave, ['stock_total', 'unidades'], true)) {
                $datos['disponible'] = (int) $valor > 0;
                unset($datos[$clave]);
            } elseif (is_array($valor)) {
                $datos[$clave] = $this->disponibilidadPublica($valor);
            }
        }

        return $datos;
    }

    /**
     * Ficha de un producto con datos reales; es lo único que el modelo puede usar para describirlo.
     *
     * @return array<string, mixed>
     */
    public function ficha(Producto $producto, ?int $stock = null): array
    {
        return [
            'producto_id' => $producto->id,
            'nombre' => $producto->nombre,
            'precio_bs' => (float) $producto->precio_venta,
            'edad_recomendada' => $producto->edad_recomendada,
            'categoria' => $producto->categoria?->nombre,
            'marca' => $producto->marca?->nombre,
            'medidas' => $producto->dimensiones,
            'peso_kg' => $producto->peso !== null ? (float) $producto->peso : null,
            'stock_total' => $stock ?? $this->stockTotal(collect([$producto->id]))[$producto->id] ?? 0,
            'descripcion' => Str::limit((string) $producto->descripcion, 220),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buscarProductos(string $consulta, ?int $edad, ?float $precioMaximo): array
    {
        $similares = collect($this->busqueda->buscar($consulta, BusquedaSemanticaService::TIPO_PRODUCTO, 10))->keyBy('id');
        $encontrados = $this->productos($similares->keys())->sortByDesc(fn (Producto $p): float => $similares[$p->id]['similitud']);
        $motivos = $encontrados->mapWithKeys(fn (Producto $p): array => [$p->id => array_values(array_filter([
            $precioMaximo !== null && (float) $p->precio_venta > $precioMaximo ? 'supera el presupuesto de '.$precioMaximo.' Bs' : null,
            $edad !== null && ! $this->edadCompatible($p->edad_recomendada, $edad)
                ? ($p->edad_recomendada ? 'está recomendado para '.$p->edad_recomendada.' años' : 'no tiene edad recomendada confirmada') : null,
        ]))]);
        [$cumplen, $alternativas] = $encontrados->partition(fn (Producto $p): bool => $motivos[$p->id] === []);
        $stock = $this->stockTotal($encontrados->pluck('id'));

        return [
            'resultados' => $cumplen->take(5)->map(fn (Producto $p): array => $this->ficha($p, $stock[$p->id] ?? 0)
                + ['similitud' => $similares[$p->id]['similitud']])->values()->all(),
            // Si nada cumple los filtros, los más parecidos con el motivo: el asistente puede ofrecerlos con honestidad.
            'alternativas_que_no_cumplen' => $cumplen->isEmpty()
                ? $alternativas->take(3)->map(fn (Producto $p): array => $this->ficha($p, $stock[$p->id] ?? 0) + ['no_cumple' => $motivos[$p->id]])->values()->all()
                : [],
            'nota' => $cumplen->isEmpty() ? 'Ningún producto cumple todos los filtros pedidos.' : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recomendarRegalo(?int $edad, ?float $presupuesto, string $gustos, ?User $usuario): array
    {
        $productos = Producto::query()->where('estado', 'activo')->with(['categoria', 'marca'])->get();
        $stock = $this->stockTotal($productos->pluck('id'));
        $ventas = DetalleVenta::query()
            ->whereHas('venta', fn ($q) => $q->whereIn('estado', config('asistente.pronostico.estados_venta')))
            ->get(['venta_id', 'producto_id'])->groupBy('venta_id')
            ->map(fn (Collection $d): array => $d->pluck('producto_id')->unique()->values()->all())->values()->all();
        $enCarrito = $usuario ? Carrito::query()->where('user_id', $usuario->id)->where('estado', 'activo')
            ->with('detalles')->first()?->detalles->pluck('producto_id')->all() ?? [] : [];

        $solicitud = [
            'products' => $productos->map(fn (Producto $p): array => [
                'id' => $p->id, 'text' => $this->busqueda->textoProducto($p), 'price' => (float) $p->precio_venta,
                'age' => $p->edad_recomendada, 'stock' => $stock[$p->id] ?? 0,
            ])->values()->all(),
            'sales' => $ventas,
            'criteria' => ['age' => $edad, 'max_price' => $presupuesto, 'query' => $gustos, 'seed_ids' => $enCarrito],
            'limit' => 4,
        ];
        $porId = $productos->keyBy('id');
        $validarItems = fn (array $items, ?float $maximo): array => collect($items)
            ->filter(fn (array $item): bool => isset($porId[$item['id'] ?? null])
                && ($stock[$item['id']] ?? 0) > 0
                && ($edad === null || $this->edadCompatible($porId[$item['id']]->edad_recomendada, $edad))
                && ($maximo === null || (float) $porId[$item['id']]->precio_venta <= $maximo)
                && ! in_array($item['id'], $enCarrito, true))
            ->unique('id')->take(4)->values()->all();
        $items = $validarItems($this->aprendizaje->recomendar($solicitud), $presupuesto);

        // Si nada entra en el presupuesto, se repite sin ese filtro para ofrecer lo más cercano con el motivo.
        $alternativas = $items === [] && $presupuesto !== null
            ? $validarItems($this->aprendizaje->recomendar(array_replace_recursive($solicitud, ['criteria' => ['max_price' => null], 'limit' => 3])), null)
            : [];

        return [
            'recomendaciones' => collect($items)->map(fn (array $item): array => $this->ficha($porId[$item['id']], $stock[$item['id']] ?? 0)
                + ['motivos' => $item['reasons'], 'puntaje_afinidad' => $item['score'] ?? null])->all(),
            'alternativas_que_no_cumplen' => collect($alternativas)->map(fn (array $item): array => $this->ficha($porId[$item['id']], $stock[$item['id']] ?? 0)
                + ['no_cumple' => ['supera el presupuesto de '.$presupuesto.' Bs']])->all(),
            'nota' => $items === [] ? 'Ningún producto con stock cumple todos los criterios pedidos.' : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function disponibilidad(string $nombre): array
    {
        $producto = $this->encontrarProducto($nombre);
        if (! $producto) {
            return ['nota' => 'No encontré ese producto en el catálogo.'];
        }

        $sucursales = Sucursal::query()->where('estado', true)->orderBy('nombre')->get()
            ->map(fn (Sucursal $s): array => [
                'sucursal' => $s->nombre, 'direccion' => $s->direccion, 'ciudad' => $s->ciudad,
                'unidades' => (int) $this->stock->lotes($s->id, $producto->id)->sum('cantidad_disponible'),
            ]);

        return ['producto' => $this->ficha($producto, $sucursales->sum('unidades')), 'por_sucursal' => $sucursales->all()];
    }

    /**
     * @return array<string, mixed>
     */
    private function estadoPedido(?User $usuario, mixed $numero): array
    {
        if (! $usuario) {
            return ['nota' => 'Para ver pedidos el cliente debe iniciar sesión.'];
        }

        $pedidos = Venta::query()->where('cliente_id', $usuario->id)
            ->when(is_string($numero) && $numero !== '', fn ($q) => $q->where('numero_venta', $numero))
            ->with(['entrega', 'pago', 'detalles.producto'])->latest('fecha_venta')->take(3)->get();

        return [
            'pedidos' => $pedidos->map(fn (Venta $v): array => [
                'numero' => $v->numero_venta,
                'fecha' => $v->fecha_venta?->format('d/m/Y'),
                'estado' => $v->estado,
                'total_bs' => (float) $v->total,
                'tipo_entrega' => $v->tipo_entrega,
                'entrega' => $v->entrega ? [
                    'estado' => $v->entrega->estado,
                    'fecha_programada' => $v->entrega->fecha_programada,
                ] : null,
                'productos' => $v->detalles->map(fn (DetalleVenta $d): string => $d->cantidad.' × '.$d->producto?->nombre)->all(),
            ])->all(),
            'nota' => $pedidos->isEmpty() ? 'No encontré pedidos con esos datos en la cuenta del cliente.' : null,
            'seguimiento_url' => route('tienda.seguimiento'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function informacionTienda(string $pregunta): array
    {
        $coincidencias = collect($this->busqueda->buscar($pregunta, BusquedaSemanticaService::TIPO_CONOCIMIENTO, 3));
        $articulos = BaseConocimientoIA::query()->whereIn('id', $coincidencias->pluck('id'))->get()->keyBy('id');

        return [
            'informacion' => $coincidencias->filter(fn (array $c): bool => $articulos->has($c['id']))
                ->map(fn (array $c): array => ['titulo' => $articulos[$c['id']]->titulo, 'contenido' => $articulos[$c['id']]->contenido])
                ->values()->all(),
            'sucursales' => Sucursal::query()->where('estado', true)->get(['nombre', 'direccion', 'zona', 'ciudad', 'telefono'])->toArray(),
            'nota' => $coincidencias->isEmpty() ? 'La base de conocimiento no tiene información sobre esto: sugiere contactar a la tienda.' : null,
        ];
    }

    /**
     * El detalle semanal de cada producto no cabe en el contexto del modelo: se envían totales.
     *
     * @param  array<string, mixed>  $pronostico
     * @return array<string, mixed>
     */
    private function resumirPronostico(array $pronostico): array
    {
        $porSemana = collect($pronostico['productos'])->flatMap(fn (array $p) => $p['detalle'])->groupBy('week')
            ->map(fn (Collection $s): float => round($s->sum('units'), 1));

        return [
            ...collect($pronostico)->except('productos')->all(),
            'productos' => collect($pronostico['productos'])->sortByDesc('unidades_sugeridas')->take(12)
                ->map(fn (array $p): array => collect($p)->except('detalle')->all())->values()->all(),
            'total_productos_analizados' => count($pronostico['productos']),
            'nota_resumen' => 'Se muestran hasta 12 prioridades de reposición; el total semanal incluye todos los productos.',
            'unidades_por_semana' => $porSemana->all(),
            'metodo' => 'LightGBM entrenado y validado con ventas reales (M5); temporada de Bolivia según Google Trends.',
        ];
    }

    /** @return array<string, mixed> */
    private function analizarRotacion(): array
    {
        $productos = Producto::query()->where('estado', 'activo')->with('categoria')->get();
        $stock = $this->stockTotal($productos->pluck('id'));
        $vendidas = DetalleVenta::query()->whereHas('venta', fn ($q) => $q
            ->whereIn('estado', config('asistente.pronostico.estados_venta'))
            ->whereBetween('fecha_venta', [now()->subDays(90), now()]))
            ->selectRaw('producto_id, SUM(cantidad) as unidades')->groupBy('producto_id')->pluck('unidades', 'producto_id');
        $filas = $productos->map(fn (Producto $p): array => [
            'producto' => $p->nombre, 'categoria' => $p->categoria?->nombre,
            'stock_disponible' => $stock[$p->id] ?? 0, 'unidades_vendidas_90_dias' => (int) ($vendidas[$p->id] ?? 0),
        ]);

        return [
            'periodo_dias' => 90,
            'sin_ventas_con_stock' => $filas->filter(fn (array $f): bool => $f['stock_disponible'] > 0 && $f['unidades_vendidas_90_dias'] === 0)->take(10)->values()->all(),
            'mayor_salida' => $filas->where('unidades_vendidas_90_dias', '>', 0)->sortByDesc('unidades_vendidas_90_dias')->take(10)->values()->all(),
            'nota' => 'Este análisis es descriptivo. Combínalo con el pronóstico LightGBM para estimar reposición; no garantiza ventas futuras.',
            'historia_demostracion' => Venta::query()->where('numero_venta', 'like', 'DEMO-DIV-%')->exists(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function revisarInventario(): array
    {
        $inventarios = Inventario::query()->with(['producto', 'almacen.sucursal', 'lotes' => fn ($q) => $q->where('estado', 'activo')])->get();
        $filas = $inventarios->filter(fn (Inventario $i): bool => $i->producto !== null)->map(fn (Inventario $i): array => [
            'producto' => $i->producto->nombre,
            'almacen' => $i->almacen?->nombre,
            'sucursal' => $i->almacen?->sucursal?->nombre,
            'stock' => (int) $i->lotes->sum('cantidad_disponible'),
            'stock_minimo' => (int) $i->stock_minimo,
        ]);
        $porVencer = LoteInventario::query()->where('estado', 'activo')->where('cantidad_disponible', '>', 0)
            ->whereNotNull('fecha_vencimiento')->whereDate('fecha_vencimiento', '<=', now()->addDays(30))
            ->with('inventario.producto')->get()
            ->map(fn (LoteInventario $l): array => ['producto' => $l->inventario?->producto?->nombre, 'lote' => $l->codigo_lote,
                'unidades' => (int) $l->cantidad_disponible, 'vence' => $l->fecha_vencimiento?->format('d/m/Y')]);

        return [
            'agotados' => $filas->where('stock', 0)->values()->all(),
            'bajo_minimo' => $filas->filter(fn (array $f): bool => $f['stock'] > 0 && $f['stock'] <= $f['stock_minimo'])->values()->all(),
            'lotes_por_vencer_30_dias' => $porVencer->all(),
            'total_unidades' => $filas->sum('stock'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenVentas(mixed $desde, mixed $hasta): array
    {
        $inicio = $this->fecha($desde) ?? CarbonImmutable::today()->subDays(30);
        $fin = ($this->fecha($hasta) ?? CarbonImmutable::today())->endOfDay();
        $ventas = Venta::query()->whereIn('estado', config('asistente.pronostico.estados_venta'))
            ->whereBetween('fecha_venta', [$inicio, $fin])->with('detalles.producto.categoria')->get();
        $detalles = $ventas->flatMap->detalles;

        return [
            'periodo' => ['desde' => $inicio->toDateString(), 'hasta' => $fin->toDateString()],
            'ventas' => $ventas->count(),
            'total_bs' => round((float) $ventas->sum('total'), 2),
            'ticket_promedio_bs' => $ventas->isEmpty() ? 0 : round((float) $ventas->avg('total'), 2),
            'unidades' => (int) $detalles->sum('cantidad'),
            'mas_vendidos' => $detalles->groupBy('producto_id')->map(fn (Collection $d): array => [
                'producto' => $d->first()->producto?->nombre, 'unidades' => (int) $d->sum('cantidad'), 'total_bs' => round((float) $d->sum('subtotal'), 2),
            ])->sortByDesc('unidades')->take(5)->values()->all(),
            'por_categoria' => $detalles->groupBy(fn (DetalleVenta $d) => $d->producto?->categoria?->nombre ?? 'Sin categoría')
                ->map(fn (Collection $d, string $c): array => ['categoria' => $c, 'unidades' => (int) $d->sum('cantidad'), 'total_bs' => round((float) $d->sum('subtotal'), 2)])
                ->values()->all(),
            'historia_demostracion' => $ventas->contains(fn (Venta $v): bool => ($v->cotizacion_envio['es_demostracion'] ?? false) === true),
        ];
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return Collection<int, Producto>
     */
    private function productos(Collection $ids): Collection
    {
        return Producto::query()->where('estado', 'activo')->whereIn('id', $ids)->with(['categoria', 'marca'])->get();
    }

    private function encontrarProducto(string $nombre): ?Producto
    {
        $termino = trim($nombre);
        if ($termino === '') {
            return null;
        }

        $exacto = Producto::query()->where('estado', 'activo')->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($termino).'%'])
            ->with(['categoria', 'marca'])->first();
        if ($exacto) {
            return $exacto;
        }
        $similar = $this->busqueda->buscar($termino, BusquedaSemanticaService::TIPO_PRODUCTO, 1)[0] ?? null;

        return $similar ? $this->productos(collect([$similar['id']]))->first() : null;
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return array<int, int>
     */
    private function stockTotal(Collection $ids): array
    {
        return LoteInventario::query()
            ->join('inventarios', 'inventarios.id', '=', 'lotes_inventario.inventario_id')
            ->whereNull('inventarios.deleted_at')->where('lotes_inventario.estado', 'activo')
            ->where('lotes_inventario.cantidad_disponible', '>', 0)
            ->where(fn ($q) => $q->whereNull('lotes_inventario.fecha_vencimiento')->orWhereDate('lotes_inventario.fecha_vencimiento', '>=', today()))
            ->whereHas('inventario', fn ($q) => $q->whereHas('almacen', fn ($a) => $a->where('estado', true)->whereNull('almacenes.deleted_at')
                ->whereHas('sucursal', fn ($s) => $s->where('estado', true)->whereNull('sucursales.deleted_at'))))
            ->whereIn('inventarios.producto_id', $ids)
            ->groupBy('inventarios.producto_id')
            ->selectRaw('inventarios.producto_id, SUM(lotes_inventario.cantidad_disponible) as total')
            ->pluck('total', 'producto_id')->map(fn ($t): int => (int) $t)->all();
    }

    private function edadCompatible(?string $rango, int $edad): bool
    {
        preg_match_all('/\d+/', (string) $rango, $numeros);
        $numeros = array_map('intval', $numeros[0]);
        if ($numeros === []) {
            return false;
        }
        $maximo = str_contains((string) $rango, '+') ? 99 : end($numeros);

        return $edad >= $numeros[0] && $edad <= $maximo;
    }

    /**
     * @param  array<string, array<string, mixed>>  $propiedades
     * @param  list<string>  $requeridas
     * @return array<string, mixed>
     */
    private function definir(string $nombre, string $descripcion, array $propiedades, array $requeridas = []): array
    {
        return ['type' => 'function', 'function' => [
            'name' => $nombre,
            'description' => $descripcion,
            'parameters' => ['type' => 'object', 'properties' => (object) $propiedades, 'required' => $requeridas],
        ]];
    }

    private function entero(mixed $valor): ?int
    {
        return is_numeric($valor) ? (int) $valor : null;
    }

    private function numero(mixed $valor): ?float
    {
        return is_numeric($valor) ? (float) $valor : null;
    }

    private function fecha(mixed $valor): ?CarbonImmutable
    {
        try {
            return is_string($valor) && $valor !== '' ? CarbonImmutable::parse($valor)->startOfDay() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
