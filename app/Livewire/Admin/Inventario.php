<?php

namespace App\Livewire\Admin;

use App\Services\InventarioService;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Inventario extends Component
{
    use WithPagination;

    public string $buscar = '';

    public string $filtroSucursal = '';

    public string $filtroAlmacen = '';

    public string $filtroStock = '';

    public string $filtroEstado = 'activos';

    public string $orden = 'recientes';

    public bool $mostrarIngreso = false;

    public bool $mostrarDetalle = false;

    public bool $mostrarBaja = false;

    public int $paso = 1;

    public string $buscarProducto = '';

    public string $productoId = '';

    public string $sucursalId = '';

    public string $almacenId = '';

    public string $proveedorId = '';

    public string $cantidad = '';

    public string $stockMinimo = '0';

    public string $costoUnitario = '';

    public string $margen = '';

    public string $fechaIngreso = '';

    public string $fechaVencimiento = '';

    public string $motivoBaja = '';

    public string $explicacionStockMinimo = '';

    public bool $ajustarPreciosAnteriores = true;

    /** @var array<int, array{id: int, codigo: string, costo: string, precio_venta: string, disponible: int}> */
    #[Locked]
    public array $lotesAnterioresDetectados = [];

    // Propiedades para Edición de Lote
    public bool $mostrarEdicionLote = false;

    #[Locked]
    public ?int $loteEdicionId = null;

    #[Locked]
    public string $codigoLoteEdicion = '';

    public string $costoUnitarioEdicion = '';

    public string $margenEdicion = '';

    public string $cantidadInicialEdicion = '';

    public string $cantidadDisponibleEdicion = '';

    public string $proveedorIdEdicion = '';

    public string $fechaIngresoEdicion = '';

    public string $fechaVencimientoEdicion = '';

    public string $motivoEdicion = '';

    #[Locked]
    public int $unidadesSalidasEdicion = 0;

    /** @var array<string, string> */
    #[Locked]
    public array $precioCalculadoEdicion = [];

    // Propiedades para Descuento FIFO (Exhibición / Merma)
    public bool $mostrarDescuento = false;

    #[Locked]
    public ?int $inventarioDescuentoId = null;

    public string $cantidadDescuento = '1';

    public string $motivoDescuento = 'Salida para exhibición en vitrina';

    #[Locked]
    public string $claveIngreso = '';

    #[Locked]
    public ?int $inventarioBajaId = null;

    /** @var array<string, mixed> */
    #[Locked]
    public array $productoSeleccionado = [];

    /** @var array<string, string> */
    #[Locked]
    public array $precioCalculado = [];

    /** @var array<string, mixed> */
    #[Locked]
    public array $detalle = [];

    protected InventarioService $service;

    public function boot(InventarioService $service): void
    {
        $this->service = $service;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return array_merge($this->service->reglasUbicacion($this->sucursalId), $this->service->reglasLote(), [
            'buscar' => ['nullable', 'string', 'max:100'],
            'buscarProducto' => ['nullable', 'string', 'max:100'],
            'filtroSucursal' => ['nullable', 'integer', 'min:1'],
            'filtroAlmacen' => ['nullable', 'integer', 'min:1'],
            'filtroStock' => ['nullable', Rule::in(['agotado', 'bajo', 'disponible'])],
            'filtroEstado' => ['required', Rule::in(['activos', 'archivados'])],
            'orden' => ['required', Rule::in(['recientes', 'antiguos', 'stock_asc', 'stock_desc'])],
            'motivoBaja' => ['required', 'string', 'min:10', 'max:500'],
        ]);
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return $this->service->atributos();
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return $this->service->mensajes();
    }

    public function updated(string $property): void
    {
        if ($property === 'sucursalId') {
            $this->almacenId = '';
        }
        if ($property === 'filtroSucursal') {
            $this->filtroAlmacen = '';
        }
        if (in_array($property, ['buscar', 'filtroSucursal', 'filtroAlmacen', 'filtroStock', 'filtroEstado', 'orden'], true)) {
            $this->resetPage();
        }
        if (in_array($property, ['costoUnitario', 'margen'], true)) {
            $this->precioCalculado = [];
            $this->lotesAnterioresDetectados = [];
        }
        if (in_array($property, ['costoUnitarioEdicion', 'margenEdicion'], true)) {
            $this->precioCalculadoEdicion = [];
        }

        if (array_key_exists($property, $this->service->reglasEdicionLote())) {
            $this->validateOnly($property, $this->service->reglasEdicionLote());
        } elseif (array_key_exists($property, $this->service->reglasDescuento())) {
            $this->validateOnly($property, $this->service->reglasDescuento());
        } else {
            $this->validateOnly($property);
        }

        if ($property === 'cantidadInicialEdicion') {
            $this->recalcularDisponibleEdicion();
        }

        if (in_array($property, ['costoUnitario', 'margen'], true) && $this->costoUnitario !== '' && $this->margen !== '') {
            $this->precioCalculado = $this->service->calcularPrecio($this->costoUnitario, $this->margen);
            $this->verificarPreciosAnteriores();
        }
        if (in_array($property, ['costoUnitarioEdicion', 'margenEdicion'], true) && $this->costoUnitarioEdicion !== '' && $this->margenEdicion !== '') {
            $this->precioCalculadoEdicion = $this->service->calcularPrecio($this->costoUnitarioEdicion, $this->margenEdicion);
        }
    }

    public function abrirIngreso(?int $productoId = null, ?int $sucursalId = null, ?int $almacenId = null): void
    {
        $this->cerrarIngreso();
        $this->fechaIngreso = now()->format('Y-m-d');
        $this->claveIngreso = (string) Str::uuid();
        $this->sucursalId = $sucursalId ? (string) $sucursalId : '';
        $this->almacenId = $almacenId ? (string) $almacenId : '';
        if ($productoId !== null) {
            $this->seleccionarProducto($productoId);
        }
        $this->mostrarIngreso = true;
    }

    public function seleccionarProducto(int $id): void
    {
        $this->productoSeleccionado = $this->service->obtenerProducto($id);
        $this->productoId = (string) $id;
        $this->resetErrorBag('productoId');
    }

    public function siguientePaso(): void
    {
        $this->validate($this->service->reglasUbicacion($this->sucursalId));
        $this->productoSeleccionado = $this->service->obtenerProducto((int) $this->productoId);
        $this->paso = 2;
        if ($this->stockMinimo === '0' || $this->stockMinimo === '') {
            $this->sugerirStockMinimoIO();
        }
        if ($this->costoUnitario !== '' && $this->margen !== '') {
            $this->verificarPreciosAnteriores();
        }
    }

    public function anteriorPaso(): void
    {
        $this->paso = 1;
    }

    public function sugerirStockMinimoIO(): void
    {
        if (empty($this->productoId)) {
            return;
        }

        $cantidad = ctype_digit($this->cantidad) && (int) $this->cantidad > 0 ? (int) $this->cantidad : 10;
        $proveedor = ctype_digit($this->proveedorId) && (int) $this->proveedorId > 0 ? (int) $this->proveedorId : null;

        $resultado = $this->service->calcularStockMinimoSugerido((int) $this->productoId, $cantidad, $proveedor);
        $this->stockMinimo = (string) $resultado['stock_minimo'];
        $this->explicacionStockMinimo = $resultado['explicacion'];
    }

    public function verificarPreciosAnteriores(): void
    {
        if (empty($this->productoId) || empty($this->almacenId) || empty($this->precioCalculado['precio_venta'])) {
            $this->lotesAnterioresDetectados = [];

            return;
        }

        $res = $this->service->verificarLotesAnteriores(
            (int) $this->productoId,
            (int) $this->almacenId,
            $this->precioCalculado['precio_venta']
        );

        $this->lotesAnterioresDetectados = $res['lotes'];
    }

    public function guardar(): void
    {
        $validado = $this->validate(array_merge($this->service->reglasUbicacion($this->sucursalId), $this->service->reglasLote()));
        $this->service->registrarLote($validado + [
            'claveIngreso' => $this->claveIngreso,
            'ajustarPreciosAnteriores' => $this->ajustarPreciosAnteriores,
        ]);
        $this->cerrarIngreso();
        $this->resetPage();
        $this->notificar('Lote registrado', 'El ingreso y su movimiento de existencias fueron guardados correctamente.');
    }

    public function cerrarIngreso(): void
    {
        $this->mostrarIngreso = false;
        $this->reset('productoId', 'sucursalId', 'almacenId', 'proveedorId', 'cantidad', 'stockMinimo', 'costoUnitario',
            'margen', 'fechaIngreso', 'fechaVencimiento', 'productoSeleccionado', 'precioCalculado', 'claveIngreso',
            'buscarProducto', 'paso', 'explicacionStockMinimo', 'lotesAnterioresDetectados', 'ajustarPreciosAnteriores');
        $this->resetValidation();
    }

    public function abrirEdicionLote(int $loteId): void
    {
        $lote = $this->service->obtenerLoteParaEditar($loteId);
        $this->loteEdicionId = $lote['id'];
        $this->codigoLoteEdicion = $lote['codigo'];
        $this->unidadesSalidasEdicion = (int) ($lote['unidades_salidas'] ?? 0);
        $this->cantidadInicialEdicion = (string) $lote['cantidad_inicial'];
        $this->cantidadDisponibleEdicion = (string) $lote['cantidad_disponible'];
        $this->costoUnitarioEdicion = $lote['costo_unitario'];
        $this->margenEdicion = $lote['margen'];
        $this->proveedorIdEdicion = $lote['proveedor_id'];
        $this->fechaIngresoEdicion = $lote['fecha_ingreso'];
        $this->fechaVencimientoEdicion = $lote['fecha_vencimiento'];
        $this->motivoEdicion = 'Corrección de datos del lote.';
        $this->precioCalculadoEdicion = $this->service->calcularPrecio($this->costoUnitarioEdicion, $this->margenEdicion);
        $this->resetValidation();
        $this->mostrarEdicionLote = true;
    }

    public function recalcularDisponibleEdicion(): void
    {
        if ($this->cantidadInicialEdicion !== '' && ctype_digit($this->cantidadInicialEdicion)) {
            $inicial = (int) $this->cantidadInicialEdicion;
            $this->cantidadDisponibleEdicion = (string) max(0, $inicial - $this->unidadesSalidasEdicion);
            $this->resetErrorBag('cantidadDisponibleEdicion');
        }
    }

    public function cerrarEdicionLote(): void
    {
        $this->mostrarEdicionLote = false;
        $this->reset('loteEdicionId', 'codigoLoteEdicion', 'costoUnitarioEdicion', 'margenEdicion',
            'cantidadInicialEdicion', 'cantidadDisponibleEdicion', 'proveedorIdEdicion',
            'fechaIngresoEdicion', 'fechaVencimientoEdicion', 'motivoEdicion', 'precioCalculadoEdicion',
            'unidadesSalidasEdicion');
        $this->resetValidation();
    }

    public function guardarEdicionLote(): void
    {
        if ($this->loteEdicionId === null) {
            return;
        }

        $validado = $this->validate($this->service->reglasEdicionLote());

        try {
            $this->service->actualizarLote($this->loteEdicionId, [
                'costoUnitario' => $validado['costoUnitarioEdicion'],
                'margen' => $validado['margenEdicion'],
                'cantidadInicial' => (int) $validado['cantidadInicialEdicion'],
                'cantidadDisponible' => (int) $validado['cantidadDisponibleEdicion'],
                'proveedorId' => $validado['proveedorIdEdicion'] ?: null,
                'fechaIngreso' => $validado['fechaIngresoEdicion'],
                'fechaVencimiento' => $validado['fechaVencimientoEdicion'] ?: null,
                'motivo' => $validado['motivoEdicion'],
            ]);
        } catch (ValidationException $e) {
            $mapped = [];
            foreach ($e->errors() as $key => $messages) {
                $newKey = match ($key) {
                    'costoUnitario' => 'costoUnitarioEdicion',
                    'margen' => 'margenEdicion',
                    'cantidadInicial' => 'cantidadInicialEdicion',
                    'cantidadDisponible' => 'cantidadDisponibleEdicion',
                    'proveedorId' => 'proveedorIdEdicion',
                    'fechaIngreso' => 'fechaIngresoEdicion',
                    'fechaVencimiento' => 'fechaVencimientoEdicion',
                    'motivo' => 'motivoEdicion',
                    default => $key,
                };
                $mapped[$newKey] = $messages;
            }
            throw ValidationException::withMessages($mapped);
        }

        if (! empty($this->detalle['id'])) {
            $this->detalle = $this->service->obtenerDetalle($this->detalle['id']);
        }

        $this->cerrarEdicionLote();
        $this->resetPage();
        $this->notificar('Lote actualizado', 'Los datos del lote y su registro de ajuste fueron guardados.');
    }

    public function abrirDescuento(?int $inventarioId = null): void
    {
        $id = $inventarioId ?? ($this->detalle['id'] ?? null);
        if ($id === null) {
            return;
        }

        $this->inventarioDescuentoId = $id;
        $this->cantidadDescuento = '1';
        $this->motivoDescuento = 'Salida para exhibición en vitrina';
        $this->resetValidation();
        $this->mostrarDescuento = true;
    }

    public function cerrarDescuento(): void
    {
        $this->mostrarDescuento = false;
        $this->reset('inventarioDescuentoId', 'cantidadDescuento', 'motivoDescuento');
        $this->resetValidation();
    }

    public function guardarDescuento(): void
    {
        if ($this->inventarioDescuentoId === null) {
            return;
        }

        $validado = $this->validate($this->service->reglasDescuento());

        $this->service->descontarStock($this->inventarioDescuentoId, (int) $validado['cantidadDescuento'], $validado['motivoDescuento']);

        if (! empty($this->detalle['id'])) {
            $this->detalle = $this->service->obtenerDetalle($this->detalle['id']);
        }

        $this->cerrarDescuento();
        $this->resetPage();
        $this->notificar('Existencias descontadas', 'Se registró la salida siguiendo el método FIFO.');
    }

    public function verDetalle(int $id): void
    {
        $this->detalle = $this->service->obtenerDetalle($id);
        $this->mostrarDetalle = true;
    }

    public function cerrarDetalle(): void
    {
        $this->mostrarDetalle = false;
        $this->detalle = [];
    }

    public function abrirBaja(int $id): void
    {
        $this->detalle = $this->service->obtenerDetalle($id);
        $this->inventarioBajaId = $id;
        $this->motivoBaja = '';
        $this->resetValidation();
        $this->mostrarBaja = true;
    }

    public function cerrarBaja(): void
    {
        $this->mostrarBaja = false;
        $this->reset('inventarioBajaId', 'motivoBaja', 'detalle');
        $this->resetValidation();
    }

    public function archivar(): void
    {
        if ($this->inventarioBajaId === null) {
            return;
        }
        $this->service->archivar($this->inventarioBajaId, $this->motivoBaja);
        $this->cerrarBaja();
        $this->resetPage();
        $this->notificar('Inventario archivado', 'Los lotes, movimientos e historial fueron conservados.');
    }

    public function restaurar(int $id): void
    {
        $this->service->restaurar($id);
        $this->resetPage();
        $this->notificar('Inventario restaurado', 'Puedes volver a registrar ingresos por lotes.');
    }

    public function limpiarFiltros(): void
    {
        $this->reset('buscar', 'filtroSucursal', 'filtroAlmacen', 'filtroStock', 'filtroEstado', 'orden');
        $this->resetPage();
        $this->resetValidation();
    }

    public function render(): View
    {
        $opciones = $this->service->opciones();

        return view('livewire.admin.inventario', $opciones + [
            'inventarios' => $this->service->listar([
                'buscar' => $this->buscar, 'sucursal' => $this->filtroSucursal, 'almacen' => $this->filtroAlmacen,
                'stock' => $this->filtroStock, 'estado' => $this->filtroEstado, 'orden' => $this->orden,
            ]),
            'productosDisponibles' => $this->mostrarIngreso && $this->paso === 1 ? $this->service->buscarProductos($this->buscarProducto) : [],
        ]);
    }

    private function notificar(string $titulo, string $mensaje): void
    {
        $this->dispatch('alerta-producto', titulo: $titulo, mensaje: $mensaje, icono: 'success');
    }
}
