<?php

namespace App\Livewire\Admin;

use App\Actions\Ventas\ProcesarVentaPosAction;
use App\Actions\Ventas\RegistrarClienteExpressAction;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\Venta;
use App\Services\VentaPosService;
use App\Services\VentaService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class Ventas extends Component
{
    use WithPagination;

    public string $pestana = 'pos'; // 'pos' o 'historial'

    public string $pestaña = 'pos';

    // Ubicación de venta
    public ?int $sucursalId = null;

    public ?int $almacenId = null;

    public bool $sucursalBloqueada = false;

    // Búsqueda en catálogo POS
    public string $buscarJuguete = '';

    public ?int $categoriaFiltro = null;

    // Carrito POS
    public array $carrito = [];

    // Gestión del cliente
    public string $modoCliente = 'mostrador'; // 'mostrador', 'buscar', 'nuevo'

    public string $buscarClienteTermino = '';

    public ?int $clienteId = null;

    public ?array $clienteSeleccionado = null;

    public string $nombreMostrador = 'Consumidor Final';

    public string $ciNitMostrador = '0';

    // Formulario de registro express de cliente
    public string $nuevo_nombre = '';

    public string $nuevo_apellido_paterno = '';

    public string $nuevo_apellido_materno = '';

    public string $nuevo_ci_nit = '';

    public string $nuevo_telefono = '';

    public string $nuevo_email = '';

    // Métodos de pago
    public string $metodoPago = 'efectivo'; // 'efectivo', 'qr', 'tarjeta', 'stripe'

    public float|string $montoRecibido = '';

    public string $referenciaQr = '';

    public bool $qrAcreditado = false;

    public string $referenciaTarjeta = '';

    public string $stripePaymentId = '';

    public bool $stripeAprobado = false;

    // Modal de Recibo / Ticket
    public bool $mostrarModalRecibo = false;

    public ?array $reciboActual = null;

    // Historial
    public string $buscarHistorial = '';

    public function mount(VentaPosService $posService): void
    {
        $usuario = auth()->user();
        if ($usuario?->sucursal_id) {
            $this->sucursalId = (int) $usuario->sucursal_id;
            $this->sucursalBloqueada = true;
            $sucursal = Sucursal::query()->with('almacenes')->find($this->sucursalId);
            $this->almacenId = $sucursal?->almacenes->first()?->id;
        } else {
            $sucursales = $posService->sucursalesActivas();
            if ($sucursales->isNotEmpty()) {
                $primera = $sucursales->first();
                $this->sucursalId = $primera->id;
                $this->almacenId = $primera->almacenes->first()?->id;
            }
        }
    }

    public function updatedSucursalId(): void
    {
        $usuario = auth()->user();
        if ($this->sucursalBloqueada && $usuario?->sucursal_id && (int) $this->sucursalId !== (int) $usuario->sucursal_id) {
            $this->sucursalId = (int) $usuario->sucursal_id;
            $this->notificar('Sucursal restringida', 'Solo tienes autorización para realizar ventas en tu sucursal asignada.', 'warning');

            return;
        }

        $sucursal = Sucursal::query()->with('almacenes')->find($this->sucursalId);
        $this->almacenId = $sucursal?->almacenes->first()?->id;
        // Limpiar carrito si se cambia de sucursal para evitar inconsistencias de inventario
        $this->carrito = [];
    }

    public function updatedBuscarJuguete(): void
    {
        $this->resetPage();
    }

    public function updatedCategoriaFiltro(): void
    {
        $this->resetPage();
    }

    // Acciones del Carrito
    public function agregarAlCarrito(int $productoId, VentaPosService $posService): void
    {
        $usuario = auth()->user();
        if ($this->sucursalBloqueada && $usuario?->sucursal_id && (int) $this->sucursalId !== (int) $usuario->sucursal_id) {
            $this->sucursalId = (int) $usuario->sucursal_id;
            $this->notificar('Sucursal restringida', 'Solo puedes añadir productos de tu sucursal asignada.', 'warning');

            return;
        }

        if (! $this->sucursalId) {
            $this->notificar('Sucursal no seleccionada', 'Selecciona la sucursal de venta antes de añadir juguetes.', 'warning');

            return;
        }

        $producto = Producto::query()->with(['imagenes', 'modelos3D'])->find($productoId);
        if (! $producto) {
            return;
        }

        // Obtener stock disponible en la sucursal actual
        $juguetes = $posService->buscarJuguetesParaPos($producto->codigo_producto, $this->sucursalId);
        $jugueteData = collect($juguetes)->firstWhere('id', $productoId);

        $stockDisponible = $jugueteData['stock_sucursal_actual'] ?? 0;
        if ($stockDisponible <= 0) {
            $this->notificar('Sin existencias', "El juguete '{$producto->nombre}' no tiene stock disponible en esta sucursal.", 'warning');

            return;
        }

        $precio = $jugueteData['precio_venta'] ?? (float) ($producto->precio_venta ?? 0);

        if (isset($this->carrito[$productoId])) {
            $cantActual = $this->carrito[$productoId]['cantidad'];
            if ($cantActual + 1 > $stockDisponible) {
                $this->notificar('Límite de stock', "Solo hay {$stockDisponible} unidades disponibles en esta sucursal.", 'warning');

                return;
            }
            $this->carrito[$productoId]['cantidad']++;
        } else {
            $this->carrito[$productoId] = [
                'producto_id' => $producto->id,
                'nombre' => $producto->nombre,
                'codigo' => $producto->codigo_producto,
                'imagen_url' => $jugueteData['imagen_url'] ?? null,
                'precio_unitario' => $precio,
                'cantidad' => 1,
                'stock_disponible' => $stockDisponible,
            ];
        }

        $this->sugerirEfectivoSiAplica();
    }

    public function incrementarCantidad(int $productoId): void
    {
        if (! isset($this->carrito[$productoId])) {
            return;
        }

        $item = $this->carrito[$productoId];
        if ($item['stock_disponible'] < $item['cantidad'] + 1) {
            $this->notificar('Stock máximo', "No puedes añadir más unidades que las disponibles ({$item['stock_disponible']}).", 'warning');

            return;
        }

        $this->carrito[$productoId]['cantidad']++;
        $this->sugerirEfectivoSiAplica();
    }

    public function decrementarCantidad(int $productoId): void
    {
        if (! isset($this->carrito[$productoId])) {
            return;
        }

        if ($this->carrito[$productoId]['cantidad'] > 1) {
            $this->carrito[$productoId]['cantidad']--;
        } else {
            unset($this->carrito[$productoId]);
        }

        $this->sugerirEfectivoSiAplica();
    }

    public function eliminarDelCarrito(int $productoId): void
    {
        unset($this->carrito[$productoId]);
        $this->sugerirEfectivoSiAplica();
    }

    public function limpiarCarrito(): void
    {
        $this->carrito = [];
        $this->montoRecibido = '';
        $this->referenciaQr = '';
        $this->qrAcreditado = false;
        $this->referenciaTarjeta = '';
        $this->stripePaymentId = '';
        $this->stripeAprobado = false;
    }

    public function getTotalCarritoProperty(): float
    {
        $total = 0.0;
        foreach ($this->carrito as $item) {
            $total += round($item['cantidad'] * $item['precio_unitario'], 2);
        }

        return round($total, 2);
    }

    public function getCambioProperty(): float
    {
        $recibido = is_numeric($this->montoRecibido) ? (float) $this->montoRecibido : 0.0;
        $total = $this->totalCarrito;

        return max(0.0, round($recibido - $total, 2));
    }

    private function sugerirEfectivoSiAplica(): void
    {
        if ($this->metodoPago === 'efectivo' && is_numeric($this->montoRecibido) && (float) $this->montoRecibido > 0) {
            if ((float) $this->montoRecibido < $this->totalCarrito) {
                $this->montoRecibido = $this->totalCarrito;
            }
        }
    }

    // Métodos para Efectivo
    public function establecerEfectivo(float $monto): void
    {
        $this->montoRecibido = $monto;
    }

    public function establecerEfectivoExacto(): void
    {
        $this->montoRecibido = $this->totalCarrito;
    }

    // Métodos para Simulaciones de Pagos
    public function simularAcreditacionQr(): void
    {
        $referenciaGenerada = 'QR-BCB-'.rand(100000, 999999);
        $this->referenciaQr = $referenciaGenerada;
        $this->qrAcreditado = true;
        $this->notificar('Transferencia QR Acreditada', "Acreditación bancaria exitosa confirmada. Ref: {$referenciaGenerada}", 'success');
    }

    public function simularCobroTarjeta(): void
    {
        $voucherGenerado = 'POS-VISA-'.rand(100000, 999999);
        $this->referenciaTarjeta = $voucherGenerado;
        $this->notificar('Tarjeta Autorizada', "Transacción POS aprobada por la red. Voucher: {$voucherGenerado}", 'success');
    }

    public function simularStripe(): void
    {
        $piSim = 'pi_sim_'.strtolower(Str::random(16));
        $this->stripePaymentId = $piSim;
        $this->stripeAprobado = true;
        $this->notificar('Pago Stripe Exitoso', "Simulación de cargo Stripe procesada correctamente. ID: {$piSim}", 'success');
    }

    // Clientes
    public function seleccionarCliente(int $id, VentaPosService $posService): void
    {
        $clientes = $posService->buscarClientes((string) $id);
        $cliente = collect($clientes)->firstWhere('id', $id);

        if ($cliente) {
            $this->clienteId = $cliente['id'];
            $this->clienteSeleccionado = $cliente;
            $this->modoCliente = 'buscar';
            $this->buscarClienteTermino = '';
            $this->notificar('Cliente asignado', "Venta asociada a {$cliente['nombre_completo']}", 'success');
        }
    }

    public function deseleccionarCliente(): void
    {
        $this->clienteId = null;
        $this->clienteSeleccionado = null;
        $this->modoCliente = 'mostrador';
    }

    public function registrarClienteExpress(RegistrarClienteExpressAction $registrarAction): void
    {
        try {
            $nuevoCliente = $registrarAction->ejecutar([
                'name' => $this->nuevo_nombre,
                'apellido_paterno' => $this->nuevo_apellido_paterno,
                'apellido_materno' => $this->nuevo_apellido_materno,
                'ci_nit' => $this->nuevo_ci_nit,
                'telefono' => $this->nuevo_telefono,
                'email' => $this->nuevo_email,
            ]);

            $nombreCompleto = trim("{$nuevoCliente->name} {$nuevoCliente->apellido_paterno} {$nuevoCliente->apellido_materno}");

            $this->clienteId = $nuevoCliente->id;
            $this->clienteSeleccionado = [
                'id' => $nuevoCliente->id,
                'name' => $nuevoCliente->name,
                'nombre_completo' => $nombreCompleto,
                'ci_nit' => $nuevoCliente->ci_nit ?? 'Sin CI/NIT',
                'telefono' => $nuevoCliente->telefono ?? 'Sin celular',
                'email' => $nuevoCliente->email,
            ];

            $this->modoCliente = 'buscar';
            $this->reset(['nuevo_nombre', 'nuevo_apellido_paterno', 'nuevo_apellido_materno', 'nuevo_ci_nit', 'nuevo_telefono', 'nuevo_email']);
            $this->notificar('Cliente registrado', "Cliente {$nombreCompleto} registrado y asociado a la venta.", 'success');
        } catch (ValidationException $e) {
            throw $e;
        }
    }

    // Procesar Venta
    public function procesarVenta(ProcesarVentaPosAction $procesarAction): void
    {
        if (empty($this->carrito)) {
            $this->notificar('Carrito vacío', 'Añade productos al carrito antes de cobrar.', 'warning');

            return;
        }

        if (! $this->sucursalId) {
            $this->notificar('Sucursal requerida', 'Debes seleccionar la sucursal donde se despacha la venta.', 'warning');

            return;
        }

        $items = array_values(array_map(fn ($item): array => [
            'producto_id' => (int) $item['producto_id'],
            'cantidad' => (int) $item['cantidad'],
            'precio_unitario' => (float) $item['precio_unitario'],
        ], $this->carrito));

        $montoRecibido = is_numeric($this->montoRecibido) ? (float) $this->montoRecibido : 0.0;
        $referencia = match ($this->metodoPago) {
            'qr' => $this->referenciaQr,
            'tarjeta' => $this->referenciaTarjeta,
            'stripe' => $this->stripePaymentId,
            default => null,
        };

        try {
            $resultado = $procesarAction->ejecutar(
                usuarioVendedorId: (int) auth()->id(),
                sucursalId: (int) $this->sucursalId,
                almacenId: $this->almacenId ? (int) $this->almacenId : null,
                clienteId: $this->clienteId,
                items: $items,
                metodoPago: $this->metodoPago,
                montoRecibido: $montoRecibido,
                referenciaPago: $referencia,
                stripePaymentId: $this->metodoPago === 'stripe' ? $this->stripePaymentId : null,
                nombreClienteMostrador: $this->modoCliente === 'mostrador' ? $this->nombreMostrador : null,
                ciNitClienteMostrador: $this->modoCliente === 'mostrador' ? $this->ciNitMostrador : null
            );

            $this->reciboActual = $resultado['recibo'];
            $this->mostrarModalRecibo = true;

            $this->limpiarCarrito();
            $this->deseleccionarCliente();
            $this->notificar('¡Venta completada!', "Comprobante {$resultado['recibo']['numero_venta']} emitido con éxito.", 'success');
        } catch (ValidationException $e) {
            $primerError = collect($e->errors())->flatten()->first();
            $this->notificar('No se pudo procesar la venta', $primerError, 'warning');
            throw $e;
        }
    }

    public function cerrarModalRecibo(): void
    {
        $this->mostrarModalRecibo = false;
        $this->reciboActual = null;
    }

    public function iniciarNuevaVenta(): void
    {
        $this->cerrarModalRecibo();
        $this->limpiarCarrito();
        $this->deseleccionarCliente();
    }

    public function cambiarPestana(string $nuevaPestana): void
    {
        $this->pestana = $nuevaPestana;
        $this->pestaña = $nuevaPestana;
        $this->resetPage();
    }

    public function cambiarPestaña(string $nuevaPestaña): void
    {
        $this->cambiarPestana($nuevaPestaña);
    }

    public function render(VentaPosService $posService, VentaService $ventaService): View
    {
        $sucursales = $posService->sucursalesActivas();
        $categorias = Categoria::query()->where('estado', true)->orderBy('nombre')->get(['id', 'nombre']);

        $juguetes = [];
        if ($this->sucursalId) {
            $juguetes = $posService->buscarJuguetesParaPos(
                $this->buscarJuguete,
                $this->sucursalId,
                $this->categoriaFiltro
            );
        }

        $clientesBusqueda = [];
        if ($this->modoCliente === 'buscar' && trim($this->buscarClienteTermino) !== '') {
            $clientesBusqueda = $posService->buscarClientes($this->buscarClienteTermino);
        }

        $ventasHistorial = null;
        if ($this->pestaña === 'historial') {
            $ventasHistorial = Venta::query()
                ->with(['cliente', 'vendedor', 'pago'])
                ->when(trim($this->buscarHistorial) !== '', function ($q): void {
                    $termino = trim($this->buscarHistorial);
                    $q->where(function ($sub) use ($termino): void {
                        $sub->where('numero_venta', 'like', "%{$termino}%")
                            ->orWhereHas('cliente', fn ($c) => $c->where('name', 'like', "%{$termino}%")->orWhere('ci_nit', 'like', "%{$termino}%"));
                    });
                })
                ->latest('fecha_venta')
                ->paginate(10);
        }

        return view('livewire.admin.ventas', [
            'sucursales' => $sucursales,
            'categorias' => $categorias,
            'juguetes' => $juguetes,
            'clientesBusqueda' => $clientesBusqueda,
            'ventasHistorial' => $ventasHistorial,
            'sucursalBloqueada' => $this->sucursalBloqueada,
        ]);
    }

    private function notificar(string $titulo, string $mensaje, string $icono = 'info'): void
    {
        $this->dispatch('alerta-producto', titulo: $titulo, mensaje: $mensaje, icono: $icono);
    }
}
