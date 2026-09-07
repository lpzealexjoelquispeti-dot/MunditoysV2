<?php

namespace App\Services;

use App\Actions\Inventario\ActualizarLoteAction;
use App\Actions\Inventario\AjustarPrecioLotesAnterioresAction;
use App\Actions\Inventario\ArchivarInventarioAction;
use App\Actions\Inventario\DescontarStockFifoAction;
use App\Actions\Inventario\RegistrarLoteAction;
use App\Actions\Inventario\RestaurarInventarioAction;
use App\Models\DetalleVenta;
use App\Models\Inventario;
use App\Models\LoteInventario;
use App\Repositories\InventarioRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InventarioService
{
    public function __construct(
        private InventarioRepository $repository,
        private PrecioLoteService $precios,
        private CalculoStockMinimoService $calculoStockMinimo,
        private RegistrarLoteAction $registrarAction,
        private ActualizarLoteAction $actualizarLoteAction,
        private DescontarStockFifoAction $descontarStockFifoAction,
        private AjustarPrecioLotesAnterioresAction $ajustarPreciosAction,
        private ArchivarInventarioAction $archivarAction,
        private RestaurarInventarioAction $restaurarAction,
    ) {}

    /** @param array<string, mixed> $filtros */
    public function listar(array $filtros = [], int $porPagina = 15): LengthAwarePaginator
    {
        Gate::authorize('viewAny', Inventario::class);

        return $this->repository->paginar($filtros, $porPagina);
    }

    /** @return array<string, mixed> */
    public function opciones(): array
    {
        Gate::authorize('viewAny', Inventario::class);

        return $this->repository->opciones();
    }

    /** @return array<int, array<string, mixed>> */
    public function buscarProductos(string $buscar): array
    {
        Gate::authorize('create', Inventario::class);

        return $this->repository->buscarProductos(mb_substr($buscar, 0, 100));
    }

    /** @return array<string, mixed> */
    public function obtenerProducto(int $id): array
    {
        Gate::authorize('create', Inventario::class);

        return $this->repository->obtenerProducto($id);
    }

    /** @return array<string, array<int, mixed>> */
    public function reglasUbicacion(string $sucursalId): array
    {
        return [
            'productoId' => ['bail', 'required', 'integer', Rule::exists('productos', 'id')->whereNull('deleted_at')->where('estado', 'activo')],
            'sucursalId' => ['bail', 'required', 'integer', Rule::exists('sucursales', 'id')->whereNull('deleted_at')->where('estado', true)],
            'almacenId' => ['bail', 'required', 'integer', Rule::exists('almacenes', 'id')->whereNull('deleted_at')->where('estado', true)->where('sucursal_id', ctype_digit($sucursalId) ? (int) $sucursalId : 0)],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public function reglasLote(): array
    {
        return [
            'cantidad' => ['bail', 'required', 'integer', 'min:1', 'max:1000000'],
            'stockMinimo' => ['bail', 'required', 'integer', 'min:0', 'max:1000000'],
            'costoUnitario' => ['bail', 'required', 'regex:/\A\d{1,8}(?:\.\d{1,2})?\z/', 'numeric', 'min:0.01', 'max:99999999.99'],
            'margen' => ['bail', 'required', 'regex:/\A\d{1,2}(?:\.\d{1,2})?\z/', 'numeric', 'min:0.01', 'max:99.99'],
            'fechaIngreso' => ['bail', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'fechaVencimiento' => ['bail', 'nullable', 'date_format:Y-m-d', 'after:today', 'after:fechaIngreso'],
            'proveedorId' => ['bail', 'nullable', 'integer', Rule::exists('proveedores', 'id')->whereNull('deleted_at')->where('estado', true)],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public function reglasEdicionLote(): array
    {
        return [
            'cantidadInicialEdicion' => ['bail', 'required', 'integer', 'min:1', 'max:1000000'],
            'cantidadDisponibleEdicion' => ['bail', 'required', 'integer', 'min:0', 'max:1000000', 'lte:cantidadInicialEdicion'],
            'costoUnitarioEdicion' => ['bail', 'required', 'regex:/\A\d{1,8}(?:\.\d{1,2})?\z/', 'numeric', 'min:0.01', 'max:99999999.99'],
            'margenEdicion' => ['bail', 'required', 'regex:/\A\d{1,2}(?:\.\d{1,2})?\z/', 'numeric', 'min:0.01', 'max:99.99'],
            'fechaIngresoEdicion' => ['bail', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'fechaVencimientoEdicion' => ['bail', 'nullable', 'date_format:Y-m-d', 'after:today', 'after:fechaIngresoEdicion'],
            'proveedorIdEdicion' => ['bail', 'nullable', 'integer', Rule::exists('proveedores', 'id')->whereNull('deleted_at')->where('estado', true)],
            'motivoEdicion' => ['bail', 'required', 'string', 'min:5', 'max:255'],
        ];
    }

    /** @return array<string, array<int, mixed>> */
    public function reglasDescuento(): array
    {
        return [
            'cantidadDescuento' => ['bail', 'required', 'integer', 'min:1', 'max:1000000'],
            'motivoDescuento' => ['bail', 'required', 'string', 'min:3', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public function atributos(): array
    {
        return [
            'productoId' => 'producto', 'sucursalId' => 'sucursal', 'almacenId' => 'almacén',
            'cantidad' => 'cantidad de ingreso', 'stockMinimo' => 'stock mínimo',
            'costoUnitario' => 'costo unitario', 'margen' => 'margen sobre venta',
            'fechaIngreso' => 'fecha de ingreso', 'fechaVencimiento' => 'fecha de vencimiento',
            'proveedorId' => 'proveedor', 'claveIngreso' => 'identificador de ingreso',
            'motivoBaja' => 'motivo del archivado',
            'cantidadInicial' => 'cantidad inicial', 'cantidadDisponible' => 'cantidad disponible',
            'cantidadInicialEdicion' => 'cantidad inicial', 'cantidadDisponibleEdicion' => 'cantidad disponible',
            'costoUnitarioEdicion' => 'costo unitario', 'margenEdicion' => 'margen sobre venta',
            'fechaIngresoEdicion' => 'fecha de ingreso', 'fechaVencimientoEdicion' => 'fecha de vencimiento',
            'proveedorIdEdicion' => 'proveedor', 'motivoEdicion' => 'motivo de la corrección',
            'cantidadDescuento' => 'cantidad a descontar', 'motivoDescuento' => 'motivo de la salida',
            'motivo' => 'motivo de la corrección',
        ];
    }

    /** @return array<string, string> */
    public function mensajes(): array
    {
        return [
            'productoId.exists' => 'Selecciona un producto activo y disponible.',
            'almacenId.exists' => 'Selecciona un almacén activo de la sucursal indicada.',
            'sucursalId.exists' => 'Selecciona una sucursal activa.',
            'proveedorId.exists' => 'Selecciona un proveedor activo.',
            'proveedorIdEdicion.exists' => 'Selecciona un proveedor activo.',
            'costoUnitario.regex' => 'El costo debe ser un número positivo con hasta 2 decimales.',
            'costoUnitario.required' => 'El costo unitario es obligatorio.',
            'costoUnitario.min' => 'El costo unitario debe ser mayor a 0.',
            'margen.regex' => 'El margen debe ser un porcentaje con hasta 2 decimales.',
            'margen.required' => 'El margen sobre venta es obligatorio.',
            'margen.min' => 'El margen debe ser mayor a 0 %.',
            'margen.max' => 'El margen debe ser menor que 100 %.',
            'fechaIngreso.required' => 'La fecha de ingreso es obligatoria.',
            'fechaIngreso.before_or_equal' => 'La fecha de ingreso no puede ser futura.',
            'fechaVencimiento.after' => 'El vencimiento debe ser posterior a hoy y al ingreso.',
            'cantidadInicial.required' => 'La cantidad inicial es obligatoria.',
            'cantidadInicial.min' => 'La cantidad inicial debe ser al menos 1.',
            'cantidadDisponible.required' => 'La cantidad disponible es obligatoria.',
            'motivo.required' => 'El motivo de la corrección es obligatorio.',
            'motivo.min' => 'Explica el motivo de la corrección con al menos 5 caracteres.',
            'cantidadInicialEdicion.required' => 'La cantidad inicial es obligatoria.',
            'cantidadInicialEdicion.min' => 'La cantidad inicial debe ser al menos 1.',
            'cantidadDisponibleEdicion.required' => 'La cantidad disponible es obligatoria.',
            'cantidadDisponibleEdicion.min' => 'La cantidad disponible debe ser al menos 0.',
            'cantidadDisponibleEdicion.lte' => 'La cantidad disponible no puede ser mayor que la cantidad inicial.',
            'costoUnitarioEdicion.required' => 'El costo unitario es obligatorio.',
            'costoUnitarioEdicion.regex' => 'El costo debe ser un número positivo con hasta 2 decimales.',
            'costoUnitarioEdicion.min' => 'El costo unitario debe ser mayor a 0.',
            'margenEdicion.required' => 'El margen sobre venta es obligatorio.',
            'margenEdicion.regex' => 'El margen debe ser un porcentaje con hasta 2 decimales.',
            'margenEdicion.min' => 'El margen debe ser mayor a 0 %.',
            'margenEdicion.max' => 'El margen debe ser menor a 100 %.',
            'fechaIngresoEdicion.required' => 'La fecha de ingreso es obligatoria.',
            'fechaIngresoEdicion.before_or_equal' => 'La fecha de ingreso no puede ser futura.',
            'fechaVencimientoEdicion.after' => 'El vencimiento debe ser posterior a hoy y al ingreso.',
            'motivoEdicion.required' => 'El motivo de la corrección es obligatorio.',
            'motivoEdicion.min' => 'Explica el motivo de la corrección con al menos 5 caracteres.',
            'cantidadDescuento.required' => 'La cantidad a descontar es obligatoria.',
            'cantidadDescuento.min' => 'Debes descontar al menos 1 unidad.',
            'motivoDescuento.required' => 'El motivo de la salida es obligatorio.',
            'motivoDescuento.min' => 'Indica el motivo de la salida.',
        ];
    }

    /** @return array<string, string> */
    public function calcularPrecio(string $costo, string $margen): array
    {
        return $this->precios->calcular($costo, $margen);
    }

    /** @param array<string, mixed> $datos */
    public function registrarLote(array $datos): LoteInventario
    {
        Gate::authorize('create', Inventario::class);
        $validado = Validator::make($datos, array_merge(
            $this->reglasUbicacion((string) ($datos['sucursalId'] ?? '')), $this->reglasLote(),
            [
                'claveIngreso' => ['required', 'uuid'],
                'ajustarPreciosAnteriores' => ['nullable', 'boolean'],
            ],
        ), $this->mensajes(), $this->atributos())->validate();
        $precios = $this->precios->calcular((string) $validado['costoUnitario'], (string) $validado['margen']);
        $usuarioId = (int) auth()->id();

        return DB::transaction(function () use ($validado, $precios, $usuarioId): LoteInventario {
            $this->repository->bloquearUbicacionProducto((int) $validado['productoId'], (int) $validado['almacenId'], (int) $validado['sucursalId']);
            if (! empty($validado['proveedorId']) && ! $this->repository->proveedorActivoBloqueado((int) $validado['proveedorId'])) {
                throw ValidationException::withMessages(['proveedorId' => 'El proveedor dejó de estar disponible. Selecciona otro proveedor activo.']);
            }
            $inventario = $this->repository->inventarioParaIngreso((int) $validado['productoId'], (int) $validado['almacenId'], (int) $validado['stockMinimo']);
            $inventario = $this->repository->buscarBloqueado($inventario->id);
            if ($inventario->trashed()) {
                throw ValidationException::withMessages(['productoId' => 'Este inventario está archivado. Restáuralo desde el filtro Archivados antes de ingresar otro lote.']);
            }
            $anterior = $this->repository->buscarIngreso($validado['claveIngreso']);
            if ($anterior !== null) {
                if ($anterior->inventario_id !== $inventario->id || $anterior->registrado_por !== $usuarioId
                    || $anterior->cantidad_inicial !== (int) $validado['cantidad']
                    || $anterior->costo_unitario !== $precios['costo_unitario']
                    || $anterior->margen_porcentaje !== $precios['margen_porcentaje']
                    || (int) $anterior->proveedor_id !== (int) ($validado['proveedorId'] ?? 0)
                    || $anterior->fecha_ingreso->format('Y-m-d') !== $validado['fechaIngreso']
                    || $anterior->fecha_vencimiento?->format('Y-m-d') !== (($validado['fechaVencimiento'] ?? null) ?: null)) {
                    throw ValidationException::withMessages(['claveIngreso' => 'Este ingreso ya fue registrado. Abre un formulario nuevo para agregar otro lote.']);
                }

                return $anterior;
            }

            $loteCreado = $this->registrarAction->ejecutar([
                'inventario_id' => $inventario->id, 'proveedor_id' => ($validado['proveedorId'] ?? null) ?: null,
                'codigo_lote' => 'MTL-'.Str::ulid(), 'clave_ingreso' => $validado['claveIngreso'],
                'cantidad_inicial' => (int) $validado['cantidad'], 'cantidad_disponible' => (int) $validado['cantidad'],
                'costo_unitario' => $precios['costo_unitario'], 'precio_venta' => $precios['precio_venta'],
                'margen_porcentaje' => $precios['margen_porcentaje'],
                'fecha_ingreso' => $validado['fechaIngreso'], 'fecha_vencimiento' => ($validado['fechaVencimiento'] ?? null) ?: null,
                'estado' => 'activo', 'registrado_por' => $usuarioId,
            ], $usuarioId);

            if (! empty($validado['ajustarPreciosAnteriores'])) {
                $this->ajustarPreciosAction->ejecutar($inventario->id, $precios['precio_venta'], $usuarioId, $loteCreado->id);
            }

            return $loteCreado;
        }, 3);
    }

    public function archivar(int $id, string $motivo): void
    {
        Validator::make(['motivoBaja' => trim($motivo)], [
            'motivoBaja' => ['required', 'string', 'min:10', 'max:500'],
        ], [], $this->atributos())->validate();

        DB::transaction(function () use ($id, $motivo): void {
            $inventario = $this->repository->buscarBloqueado($id);
            Gate::authorize('archive', $inventario);
            if ($this->repository->stockTotal($inventario) !== 0) {
                throw ValidationException::withMessages(['motivoBaja' => 'No se puede archivar un inventario con existencias. Registra primero los movimientos de salida correspondientes.']);
            }
            $historial = $inventario->historial_estado ?? [];
            $historial[] = ['accion' => 'archivado', 'motivo' => trim($motivo), 'usuario_id' => auth()->id(), 'fecha' => now()->toIso8601String()];
            $this->archivarAction->ejecutar($inventario, $historial);
        });
    }

    public function restaurar(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $inventario = $this->repository->buscarBloqueado($id);
            Gate::authorize('restore', $inventario);
            $historial = $inventario->historial_estado ?? [];
            $historial[] = ['accion' => 'restaurado', 'motivo' => 'Reactivación del registro de inventario.', 'usuario_id' => auth()->id(), 'fecha' => now()->toIso8601String()];
            $this->restaurarAction->ejecutar($inventario, $historial);
        });
    }

    /**
     * Calcula el stock mínimo óptimo utilizando modelos de Investigación Operativa.
     *
     * @return array{
     *     stock_minimo: int,
     *     metodo: string,
     *     lead_time_dias: int,
     *     demanda_diaria_promedio: float,
     *     desviacion_estandar: float,
     *     explicacion: string
     * }
     */
    public function calcularStockMinimoSugerido(int $productoId, int $cantidad, ?int $proveedorId = null): array
    {
        Gate::authorize('create', Inventario::class);

        return $this->calculoStockMinimo->calcular($productoId, $cantidad, $proveedorId);
    }

    /**
     * Verifica si existen lotes anteriores con stock para el producto y almacén a menor precio.
     *
     * @return array{
     *     hay_lotes_anteriores_menores: bool,
     *     lotes: array<int, array{id: int, codigo: string, costo: string, precio_venta: string, disponible: int}>,
     *     total_unidades: int
     * }
     */
    public function verificarLotesAnteriores(int $productoId, int $almacenId, string $nuevoPrecio): array
    {
        Gate::authorize('create', Inventario::class);

        $inventario = $this->repository->obtenerInventarioPorProductoYAlmacen($productoId, $almacenId);
        if (! $inventario) {
            return ['hay_lotes_anteriores_menores' => false, 'lotes' => [], 'total_unidades' => 0];
        }

        $lotes = $this->repository->lotesActivosConStock($inventario->id)
            ->filter(fn (LoteInventario $lote) => (float) $lote->precio_venta < (float) $nuevoPrecio);

        return [
            'hay_lotes_anteriores_menores' => $lotes->isNotEmpty(),
            'lotes' => $lotes->map(fn (LoteInventario $lote) => [
                'id' => $lote->id,
                'codigo' => $lote->codigo_lote,
                'costo' => (string) $lote->costo_unitario,
                'precio_venta' => (string) $lote->precio_venta,
                'disponible' => (int) $lote->cantidad_disponible,
            ])->values()->all(),
            'total_unidades' => (int) $lotes->sum('cantidad_disponible'),
        ];
    }

    /** @return array<string, mixed> */
    public function obtenerLoteParaEditar(int $loteId): array
    {
        $lote = $this->repository->buscarLote($loteId);
        Gate::authorize('update', $lote->inventario);

        $movimientosSalida = $lote->movimientos()
            ->where('tipo_movimiento', 'salida')
            ->get();

        $salidasMovimientos = (int) $movimientosSalida->sum('cantidad');
        $movimientosVentaIds = $movimientosSalida->where('referencia_tipo', 'venta')->pluck('referencia_id')->filter()->all();
        $salidasVentasDirectas = (int) DetalleVenta::query()
            ->where('lote_id', $loteId)
            ->when(! empty($movimientosVentaIds), fn ($q) => $q->whereNotIn('venta_id', $movimientosVentaIds))
            ->sum('cantidad');

        $totalSalidas = $salidasMovimientos + $salidasVentasDirectas;
        $disponibleCalculado = max(0, $lote->cantidad_inicial - $totalSalidas);

        $disponibleFinal = ($lote->cantidad_disponible === 0 && $disponibleCalculado > 0)
            ? $disponibleCalculado
            : $lote->cantidad_disponible;

        return [
            'id' => $lote->id,
            'inventario_id' => $lote->inventario_id,
            'codigo' => $lote->codigo_lote,
            'cantidad_inicial' => $lote->cantidad_inicial,
            'cantidad_disponible' => $disponibleFinal,
            'unidades_salidas' => $totalSalidas,
            'costo_unitario' => (string) $lote->costo_unitario,
            'margen' => (string) $lote->margen_porcentaje,
            'precio_venta' => (string) $lote->precio_venta,
            'proveedor_id' => $lote->proveedor_id ? (string) $lote->proveedor_id : '',
            'fecha_ingreso' => $lote->fecha_ingreso->format('Y-m-d'),
            'fecha_vencimiento' => $lote->fecha_vencimiento?->format('Y-m-d') ?? '',
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function actualizarLote(int $loteId, array $datos): LoteInventario
    {
        $lote = $this->repository->buscarLoteBloqueado($loteId);
        Gate::authorize('update', $lote->inventario);

        $validado = Validator::make($datos, [
            'costoUnitario' => ['bail', 'required', 'regex:/\A\d{1,8}(?:\.\d{1,2})?\z/', 'numeric', 'min:0.01', 'max:99999999.99'],
            'margen' => ['bail', 'required', 'regex:/\A\d{1,2}(?:\.\d{1,2})?\z/', 'numeric', 'min:0.01', 'max:99.99'],
            'cantidadInicial' => ['bail', 'required', 'integer', 'min:1', 'max:1000000'],
            'cantidadDisponible' => ['bail', 'required', 'integer', 'min:0', 'max:1000000', 'lte:cantidadInicial'],
            'proveedorId' => ['bail', 'nullable', 'integer', Rule::exists('proveedores', 'id')->whereNull('deleted_at')->where('estado', true)],
            'fechaIngreso' => ['bail', 'required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'fechaVencimiento' => ['bail', 'nullable', 'date_format:Y-m-d', 'after:today', 'after:fechaIngreso'],
            'motivo' => ['bail', 'required', 'string', 'min:5', 'max:255'],
        ], $this->mensajes(), $this->atributos())->validate();

        if ($validado['cantidadDisponible'] > $validado['cantidadInicial']) {
            throw ValidationException::withMessages([
                'cantidadDisponible' => 'La cantidad disponible no puede ser mayor que la cantidad inicial del lote.',
            ]);
        }

        $precios = $this->precios->calcular((string) $validado['costoUnitario'], (string) $validado['margen']);
        $usuarioId = (int) auth()->id();

        return $this->actualizarLoteAction->ejecutar($lote, [
            'costo_unitario' => $precios['costo_unitario'],
            'margen_porcentaje' => $precios['margen_porcentaje'],
            'precio_venta' => $precios['precio_venta'],
            'cantidad_inicial' => (int) $validado['cantidadInicial'],
            'cantidad_disponible' => (int) $validado['cantidadDisponible'],
            'proveedor_id' => ! empty($validado['proveedorId']) ? (int) $validado['proveedorId'] : null,
            'fecha_ingreso' => $validado['fechaIngreso'],
            'fecha_vencimiento' => ! empty($validado['fechaVencimiento']) ? $validado['fechaVencimiento'] : null,
            'motivo' => $validado['motivo'] ?? 'Corrección manual de datos del lote.',
        ], $usuarioId);
    }

    /**
     * Descuenta existencias siguiendo el método FIFO para exhibición, mermas o muestras.
     *
     * @return array<int, array{lote_id: int, descontado: int, restante_lote: int}>
     */
    public function descontarStock(int $inventarioId, int $cantidad, string $motivo): array
    {
        $inventario = $this->repository->buscarBloqueado($inventarioId);
        Gate::authorize('update', $inventario);

        Validator::make(compact('cantidad', 'motivo'), [
            'cantidad' => ['required', 'integer', 'min:1', 'max:1000000'],
            'motivo' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'cantidad.min' => 'Debes descontar al menos 1 unidad.',
            'motivo.min' => 'Indica el motivo de la salida (ej. exhibición en vitrina, merma, muestra).',
        ])->validate();

        $usuarioId = (int) auth()->id();

        return $this->descontarStockFifoAction->ejecutar($inventario, $cantidad, $motivo, $usuarioId);
    }

    /** @return array<string, mixed> */
    public function obtenerDetalle(int $id): array
    {
        $inventario = $this->repository->detalle($id);
        Gate::authorize('view', $inventario);

        return [
            'id' => $inventario->id, 'producto' => $inventario->producto?->nombre ?? 'Producto no disponible',
            'sucursal' => $inventario->almacen?->sucursal?->nombre ?? 'Sucursal no disponible',
            'almacen' => $inventario->almacen?->nombre ?? 'Almacén no disponible',
            'stock' => $this->repository->stockTotal($inventario), 'stock_minimo' => $inventario->stock_minimo,
            'archivado' => $inventario->trashed(), 'historial' => $inventario->historial_estado ?? [],
            'lotes' => $inventario->lotes->map(fn (LoteInventario $lote): array => [
                'id' => $lote->id, 'codigo' => $lote->codigo_lote, 'inicial' => $lote->cantidad_inicial,
                'disponible' => $lote->cantidad_disponible, 'costo' => $lote->costo_unitario,
                'precio' => $lote->precio_venta, 'margen' => $lote->margen_porcentaje,
                'fecha' => $lote->fecha_ingreso->format('d/m/Y'), 'vencimiento' => $lote->fecha_vencimiento?->format('d/m/Y'),
                'proveedor' => $lote->proveedor?->empresa ?? 'Sin proveedor', 'estado' => $lote->trashed() ? 'Archivado' : $lote->estado,
                'movimientos' => $lote->movimientos->map(fn ($movimiento): array => [
                    'tipo' => $movimiento->tipo_movimiento, 'cantidad' => $movimiento->cantidad,
                    'anterior' => $movimiento->cantidad_anterior, 'nueva' => $movimiento->cantidad_nueva,
                    'motivo' => $movimiento->motivo ?? '',
                    'usuario' => $movimiento->usuario?->name ?? 'Usuario no disponible', 'fecha' => $movimiento->created_at->format('d/m/Y H:i'),
                ])->all(),
            ])->all(),
        ];
    }
}
