<?php

namespace App\Livewire\Cliente;

use App\Actions\Ventas\GenerarFacturaPdfAction;
use App\Mail\FacturaCompraMailable;
use App\Models\DetalleVenta;
use App\Models\Entrega;
use App\Models\Inventario;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\CarritoService;
use App\Services\QrCodeService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Finalizar Compra - MundiToys')]
class Checkout extends Component
{
    // Asistente por pasos (Wizard)
    public int $pasoActual = 1; // 1: Sucursal & Recojo, 2: Facturación, 3: Pago Stripe

    // Subplanillas de despacho
    public string $tipoEntrega = 'recojo_tienda'; // 'recojo_tienda' (Subplanilla 1) o 'delivery' (Subplanilla 2)

    // Subplanilla 1: Recojo en Sucursal
    public ?int $sucursalId = null;

    public float $sucursalLat = -16.5134811;

    public float $sucursalLng = -68.1275511;

    public string $sucursalNombre = '';

    public string $sucursalDireccion = '';

    public string $nombreRetira = '';

    public string $ciRetira = '';

    public string $telefonoRetira = '';

    public string $notasRecojo = '';

    // Datos de Facturación Oficial
    public string $facturaNombre = '';

    public string $facturaNitCi = '';

    public string $facturaEmail = '';

    // Pasarela de Pago Stripe
    public string $numeroTarjeta = '';

    public string $nombreTarjeta = '';

    public string $mesExpiracion = '';

    public string $anoExpiracion = '';

    public string $cvcTarjeta = '';

    public bool $procesando = false;

    public ?string $mensajeError = null;

    public function mount(CarritoService $carritoService): void
    {
        if ($carritoService->obtenerItems()->isEmpty()) {
            $this->redirectRoute('tienda.carrito');

            return;
        }

        // Sucursal por defecto (primera activa)
        $primeraSucursal = Sucursal::query()->where('estado', true)->first();
        if ($primeraSucursal) {
            $this->sucursalId = $primeraSucursal->id;
            $this->sucursalLat = (float) ($primeraSucursal->latitud ?? -16.5134811);
            $this->sucursalLng = (float) ($primeraSucursal->longitud ?? -68.1275511);
            $this->sucursalNombre = $primeraSucursal->nombre;
            $this->sucursalDireccion = $primeraSucursal->direccion ?? '';
        }

        // Si el usuario está autenticado, precargar datos
        $usuario = Auth::user();
        if ($usuario) {
            $nombreCompleto = trim("{$usuario->name} {$usuario->apellido_paterno} {$usuario->apellido_materno}");
            $this->nombreRetira = $nombreCompleto ?: $usuario->name;
            $this->ciRetira = $usuario->ci_nit ?? '';
            $this->telefonoRetira = $usuario->telefono ?? '';
            $this->facturaNombre = $nombreCompleto ?: $usuario->name;
            $this->facturaNitCi = $usuario->ci_nit ?? '';
            $this->facturaEmail = $usuario->email ?? '';
            $this->nombreTarjeta = $nombreCompleto ?: $usuario->name;
        }
    }

    public function updatedSucursalId($val): void
    {
        $suc = Sucursal::query()->find($val);
        if ($suc) {
            $this->sucursalLat = (float) ($suc->latitud ?? -16.5134811);
            $this->sucursalLng = (float) ($suc->longitud ?? -68.1275511);
            $this->sucursalNombre = $suc->nombre;
            $this->sucursalDireccion = $suc->direccion ?? '';
            $this->dispatch('sucursal-cambiada', [
                'lat' => $this->sucursalLat,
                'lng' => $this->sucursalLng,
                'nombre' => $this->sucursalNombre,
                'direccion' => $this->sucursalDireccion,
            ]);
        }
    }

    public function updatedNombreRetira(string $val): void
    {
        if (empty($this->facturaNombre)) {
            $this->facturaNombre = $val;
        }
        if (empty($this->nombreTarjeta)) {
            $this->nombreTarjeta = $val;
        }
    }

    public function updatedCiRetira(string $val): void
    {
        if (empty($this->facturaNitCi)) {
            $this->facturaNitCi = $val;
        }
    }

    public function rellenarTarjetaPrueba(): void
    {
        $this->numeroTarjeta = '4242 4242 4242 4242';
        $this->nombreTarjeta = $this->nombreRetira ?: 'CLIENTE PRUEBA STRIPE';
        $this->mesExpiracion = '12';
        $this->anoExpiracion = '28';
        $this->cvcTarjeta = '123';
    }

    public function cambiarSubplanilla(string $tipo): void
    {
        if ($tipo === 'recojo_tienda') {
            $this->tipoEntrega = 'recojo_tienda';
        }
    }

    public function avanzarPaso1(): void
    {
        $this->validate([
            'sucursalId' => ['required', 'exists:sucursales,id'],
            'nombreRetira' => ['required', 'string', 'min:3', 'max:120'],
            'ciRetira' => ['required', 'string', 'min:4', 'max:25'],
            'telefonoRetira' => ['required', 'string', 'min:6', 'max:20'],
        ], [
            'sucursalId.required' => 'Debes seleccionar una sucursal para el recojo.',
            'sucursalId.exists' => 'La sucursal seleccionada no existe en el sistema.',
            'nombreRetira.required' => 'Ingresa el nombre completo de la persona que retirará el pedido.',
            'nombreRetira.min' => 'El nombre debe tener al menos 3 caracteres.',
            'ciRetira.required' => 'Ingresa la Cédula de Identidad (CI) de quien retira.',
            'ciRetira.min' => 'El CI debe tener al menos 4 caracteres.',
            'telefonoRetira.required' => 'Ingresa un teléfono o celular de contacto.',
            'telefonoRetira.min' => 'El teléfono debe tener al menos 6 dígitos.',
        ]);

        if (empty($this->facturaNombre)) {
            $this->facturaNombre = $this->nombreRetira;
        }
        if (empty($this->facturaNitCi)) {
            $this->facturaNitCi = $this->ciRetira;
        }
        if (empty($this->nombreTarjeta)) {
            $this->nombreTarjeta = $this->nombreRetira;
        }

        $this->pasoActual = 2;
    }

    public function avanzarPaso2(): void
    {
        $this->validate([
            'facturaNombre' => ['required', 'string', 'min:3', 'max:120'],
            'facturaNitCi' => ['required', 'string', 'min:3', 'max:25'],
            'facturaEmail' => ['required', 'email', 'max:120'],
        ], [
            'facturaNombre.required' => 'Ingresa el nombre o razón social para la factura oficial.',
            'facturaNombre.min' => 'La razón social debe tener al menos 3 caracteres.',
            'facturaNitCi.required' => 'Ingresa el NIT o CI para la factura.',
            'facturaNitCi.min' => 'El NIT o CI debe tener al menos 3 caracteres.',
            'facturaEmail.required' => 'Ingresa el correo electrónico donde recibirás la factura electrónica.',
            'facturaEmail.email' => 'Ingresa un formato de correo electrónico válido.',
        ]);

        $this->pasoActual = 3;
    }

    public function retrocederPaso(): void
    {
        if ($this->pasoActual > 1) {
            $this->pasoActual--;
        }
    }

    public function irAPaso(int $paso): void
    {
        if ($paso < $this->pasoActual) {
            $this->pasoActual = $paso;
        } elseif ($paso === 2) {
            $this->avanzarPaso1();
        } elseif ($paso === 3) {
            $this->avanzarPaso1();
            $this->avanzarPaso2();
        }
    }

    public function procesarPago(
        CarritoService $carritoService,
        GenerarFacturaPdfAction $generarFacturaPdfAction,
        QrCodeService $qrCodeService
    ): void {
        $this->mensajeError = null;

        $items = $carritoService->obtenerItems();
        if ($items->isEmpty()) {
            $this->addError('carrito', 'Tu carrito de compras está vacío.');

            return;
        }

        $this->validate([
            'sucursalId' => ['required', 'exists:sucursales,id'],
            'nombreRetira' => ['required', 'string', 'min:3', 'max:120'],
            'ciRetira' => ['required', 'string', 'min:4', 'max:25'],
            'telefonoRetira' => ['required', 'string', 'min:6', 'max:20'],
            'facturaNombre' => ['required', 'string', 'min:3', 'max:120'],
            'facturaNitCi' => ['required', 'string', 'min:3', 'max:25'],
            'facturaEmail' => ['required', 'email', 'max:120'],
            'numeroTarjeta' => ['required', 'string', 'min:14', 'max:25'],
            'nombreTarjeta' => ['required', 'string', 'min:3', 'max:100'],
            'mesExpiracion' => ['required', 'numeric', 'between:1,12'],
            'anoExpiracion' => ['required', 'numeric', 'min:24'],
            'cvcTarjeta' => ['required', 'numeric', 'digits_between:3,4'],
        ], [
            'sucursalId.required' => 'Debes seleccionar una sucursal para el recojo.',
            'nombreRetira.required' => 'Ingresa el nombre completo de quien retirará el pedido.',
            'ciRetira.required' => 'Ingresa el CI de la persona que retirará el pedido.',
            'telefonoRetira.required' => 'Ingresa un teléfono de contacto válido.',
            'facturaNombre.required' => 'Ingresa el nombre o razón social para la factura oficial.',
            'facturaNitCi.required' => 'Ingresa el NIT o CI para la factura.',
            'facturaEmail.required' => 'Ingresa un correo para recibir la factura electrónica.',
            'facturaEmail.email' => 'Ingresa un formato de correo electrónico válido.',
            'numeroTarjeta.required' => 'Ingresa el número de tarjeta.',
            'numeroTarjeta.min' => 'El número de tarjeta no es válido.',
            'nombreTarjeta.required' => 'Ingresa el nombre titular de la tarjeta.',
            'mesExpiracion.required' => 'Ingresa el mes de expiración.',
            'mesExpiracion.between' => 'El mes de expiración debe ser entre 01 y 12.',
            'anoExpiracion.required' => 'Ingresa el año de expiración.',
            'anoExpiracion.min' => 'El año de expiración no puede ser anterior al actual.',
            'cvcTarjeta.required' => 'Ingresa el código CVC de 3 o 4 dígitos.',
            'cvcTarjeta.digits_between' => 'El CVC debe contener 3 o 4 dígitos.',
        ]);

        $this->procesando = true;

        try {
            $sucursal = Sucursal::query()->findOrFail($this->sucursalId);
            $total = $carritoService->montoTotal();

            // Determinar o registrar usuario cliente
            $clienteId = Auth::id();
            if (! $clienteId) {
                $usuarioCliente = User::query()->where('email', $this->facturaEmail)->first();
                if (! $usuarioCliente) {
                    $usuarioCliente = User::query()->create([
                        'name' => $this->nombreRetira,
                        'email' => $this->facturaEmail,
                        'ci_nit' => $this->ciRetira,
                        'telefono' => $this->telefonoRetira,
                        'password' => Hash::make(Str::random(16)),
                        'tipo_usuario' => 'cliente',
                        'estado' => true,
                    ]);
                    $usuarioCliente->assignRole('Cliente');
                }
                $clienteId = $usuarioCliente->id;
            }

            // Generar número de venta oficial
            $fechaPrefijo = now()->format('Ymd');
            $conteoHoy = Venta::query()->whereDate('fecha_venta', now()->toDateString())->count() + 1;
            $numeroVenta = sprintf('VTA-%s-%04d', $fechaPrefijo, $conteoHoy);

            $venta = DB::transaction(function () use (
                $numeroVenta,
                $clienteId,
                $total,
                $items,
                $sucursal
            ): Venta {
                // 1. Crear cabecera Venta
                $venta = Venta::query()->create([
                    'numero_venta' => $numeroVenta,
                    'cliente_id' => $clienteId,
                    'usuario_vendedor_id' => null,
                    'tipo_venta' => 'online',
                    'tipo_entrega' => 'recojo_tienda',
                    'estado' => 'pagado',
                    'subtotal' => $total,
                    'descuento' => 0.00,
                    'total' => $total,
                    'fecha_venta' => now(),
                ]);

                // 2. Crear detalles de venta
                foreach ($items as $item) {
                    /** @var Producto $prod */
                    $prod = $item['producto'];
                    $cant = (int) $item['cantidad'];
                    $pu = (float) $item['precio_unitario'];

                    // Buscar inventario o lote en la sucursal seleccionada si existe
                    $loteId = null;
                    $inventario = Inventario::query()
                        ->where('producto_id', $prod->id)
                        ->whereHas('almacen', fn ($q) => $q->where('sucursal_id', $sucursal->id))
                        ->first();

                    if ($inventario) {
                        $lote = $inventario->lotes()->where('cantidad_disponible', '>', 0)->first();
                        $loteId = $lote?->id;
                    }

                    DetalleVenta::query()->create([
                        'venta_id' => $venta->id,
                        'producto_id' => $prod->id,
                        'lote_id' => $loteId,
                        'cantidad' => $cant,
                        'precio_unitario' => $pu,
                        'subtotal' => round($cant * $pu, 2),
                    ]);
                }

                // 3. Crear Entrega en Sucursal
                Entrega::query()->create([
                    'venta_id' => $venta->id,
                    'direccion_id' => null,
                    'empleado_id' => null,
                    'sucursal_origen_id' => $sucursal->id,
                    'fecha_programada' => now()->toDateString(),
                    'hora_inicio' => '09:00:00',
                    'hora_fin' => '19:00:00',
                    'estado' => 'pendiente',
                ]);

                // 4. Crear Pago Stripe
                $stripeFakeId = 'ch_stripe_'.Str::lower(Str::random(24));
                Pago::query()->create([
                    'venta_id' => $venta->id,
                    'metodo_pago' => 'stripe',
                    'referencia_pago' => "Stripe Online - Recojo en {$sucursal->nombre} por {$this->nombreRetira} (CI: {$this->ciRetira})",
                    'stripe_payment_id' => $stripeFakeId,
                    'estado' => 'completado',
                    'monto' => $total,
                    'fecha_pago' => now(),
                ]);

                // 5. Notificaciones para vendedores / administradores de la sucursal
                $vendedoresSucursal = User::query()
                    ->where('sucursal_id', $sucursal->id)
                    ->where('estado', true)
                    ->get();

                if ($vendedoresSucursal->isEmpty()) {
                    $vendedoresSucursal = User::role('Administrador')->get();
                }

                foreach ($vendedoresSucursal as $vendedor) {
                    Notificacion::query()->create([
                        'usuario_id' => $vendedor->id,
                        'titulo' => "Nuevo pedido para recojo: {$venta->numero_venta}",
                        'mensaje' => "El pedido #{$venta->numero_venta} por Bs. ".number_format($total, 2)." ha sido pagado vía Stripe y está asignado para recojo en {$sucursal->nombre} a nombre de {$this->nombreRetira}.",
                        'leido' => false,
                    ]);
                }

                // 6. Notificación para el cliente si está autenticado
                if ($clienteId) {
                    Notificacion::query()->create([
                        'usuario_id' => $clienteId,
                        'titulo' => "¡Compra confirmada! Pedido {$venta->numero_venta}",
                        'mensaje' => 'Tu compra por Bs. '.number_format($total, 2)." fue completada con éxito. Ya puedes recoger tus juguetes en la sucursal {$sucursal->nombre} presentando tu CI y código QR.",
                        'leido' => false,
                    ]);
                }

                return $venta;
            });

            // 7. Generar Factura Oficial en PDF
            $pdfWrapper = $generarFacturaPdfAction->execute($venta, $this->facturaNombre, $this->facturaNitCi);
            $pdfContenido = $pdfWrapper->output();

            // 8. Generar Código QR de Recojo
            $codigoQrContenido = "MUNDITOYS-RECOJO:{$venta->numero_venta}";
            $qrDataUri = $qrCodeService->generarDataUri($codigoQrContenido, 200);

            // 9. Enviar Factura y Notificación por Email (Mailtrap)
            try {
                Mail::to($this->facturaEmail)->send(new FacturaCompraMailable(
                    $venta,
                    $pdfContenido,
                    $qrDataUri,
                    $this->facturaNombre,
                    $this->facturaNitCi,
                    $sucursal->nombre,
                    $sucursal->direccion
                ));
            } catch (\Throwable $e) {
                Log::warning("No se pudo enviar el correo de factura para {$venta->numero_venta}: ".$e->getMessage());
            }

            // 10. Vaciar carrito de compras y redirigir a pantalla de éxito
            $carritoService->vaciar();
            $this->dispatch('carrito-actualizado');

            $this->redirectRoute('tienda.compra-exitosa', ['numeroVenta' => $venta->numero_venta]);
        } catch (\Throwable $e) {
            $this->procesando = false;
            $this->mensajeError = 'Ocurrió un inconveniente al procesar tu compra: '.$e->getMessage();
            Log::error('Error checkout: '.$e->getMessage());
        }
    }

    public function render(CarritoService $carritoService): View
    {
        $items = $carritoService->obtenerItems();
        $total = $carritoService->montoTotal();
        $sucursales = Sucursal::query()->where('estado', true)->orderBy('nombre')->get();
        $sucursalSeleccionada = $sucursales->firstWhere('id', $this->sucursalId);

        return view('livewire.cliente.checkout', [
            'items' => $items,
            'total' => $total,
            'sucursales' => $sucursales,
            'sucursalSeleccionada' => $sucursalSeleccionada,
        ]);
    }
}
