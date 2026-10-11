<?php

namespace Database\Seeders;

use App\Models\DetalleVenta;
use App\Models\Direccion;
use App\Models\DisponibilidadEmpleado;
use App\Models\Entrega;
use App\Models\HorarioEmpleado;
use App\Models\InteraccionUsuario;
use App\Models\LoteInventario;
use App\Models\MovimientoInventario;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Resena;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HistorialJuguetesSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }
        $this->call(CatalogoDiversoSeeder::class);
        $productos = Producto::query()->whereBetween('codigo_producto', ['MT-DIV-001', 'MT-DIV-024'])->orderBy('codigo_producto')->get();
        $lote = LoteInventario::query()->where('codigo_lote', 'DEMO-DIV-MT-DIV-001')->with('inventario.almacen.sucursal')->firstOrFail();
        $sucursal = $lote->inventario->almacen->sucursal;
        $admin = User::role('Administrador')->firstOrFail();
        $vendedor = $this->empleado('vendedor.diverso@example.test', 'Vendedor de demostración', 'Vendedor', $sucursal);
        $repartidor = $this->empleado('delivery.diverso@example.test', 'Repartidor de demostración', 'Logistica', $sucursal);
        foreach (range(1, 6) as $dia) {
            HorarioEmpleado::query()->firstOrCreate(['usuario_id' => $repartidor->id, 'dia_semana' => $dia], [
                'hora_inicio' => '09:00:00', 'hora_fin' => '18:00:00', 'estado' => true,
            ]);
        }
        DisponibilidadEmpleado::query()->firstOrCreate(['usuario_id' => $repartidor->id, 'fecha' => today()->toDateString()], [
            'disponible' => true, 'hora_inicio' => '09:00:00', 'hora_fin' => '18:00:00',
        ]);
        $clientes = $this->clientes();

        foreach (range(1, 52) as $semana) {
            foreach (range(0, 7) as $grupo) {
                $fecha = CarbonImmutable::now()->startOfWeek()->subWeeks(53 - $semana)->addDays($grupo % 5)->setTime(10 + $grupo % 6, 0);
                $par = collect([$productos[$grupo * 3 + $semana % 3], $productos[$grupo * 3 + ($semana + 1) % 3]]);
                $tipo = ['delivery', 'recojo_tienda', 'inmediata'][$grupo % 3];
                $cancelada = $tipo === 'delivery' && ($semana + $grupo) % 17 === 0;
                $this->venta(sprintf('DEMO-DIV-H-%02d-%02d', $semana, $grupo), $par, $clientes[$grupo], $sucursal,
                    $vendedor, $repartidor, $admin, $fecha, $tipo, $cancelada ? 'cancelado' : 'entregado');
            }
        }
        foreach (['pendiente', 'en_camino', 'entregado', 'cancelado', 'pendiente', 'entregado'] as $indice => $estado) {
            $this->venta('DEMO-DIV-A-'.($indice + 1), collect([$productos[$indice * 3], $productos[$indice * 3 + 1]]),
                $clientes[$indice], $sucursal, $vendedor, $repartidor, $admin,
                CarbonImmutable::today()->setTime(9, $indice * 5), $indice >= 4 ? 'recojo_tienda' : 'delivery', $estado);
        }
    }

    private function empleado(string $email, string $nombre, string $rol, Sucursal $sucursal): User
    {
        $usuario = User::query()->firstOrCreate(['email' => $email], [
            'name' => $nombre, 'password' => Str::random(40), 'estado' => true,
            'tipo_usuario' => 'empleado', 'sucursal_id' => $sucursal->id,
        ]);
        if ($usuario->wasRecentlyCreated) {
            $usuario->assignRole($rol);
        }

        return $usuario;
    }

    /** @return list<User> */
    private function clientes(): array
    {
        $clientes = [];
        foreach (['Ana', 'Luis', 'Carla', 'Diego', 'Lucía', 'Mateo', 'Elena', 'Bruno'] as $indice => $nombre) {
            $cliente = User::query()->firstOrCreate(['email' => 'cliente.diverso.'.($indice + 1).'@example.test'], [
                'name' => $nombre.' (demostración)', 'password' => Str::random(40), 'estado' => true, 'tipo_usuario' => 'cliente',
            ]);
            if ($cliente->wasRecentlyCreated) {
                $cliente->assignRole('Cliente');
            }
            Direccion::query()->firstOrCreate(['user_id' => $cliente->id, 'nombre_referencia' => 'Domicilio de demostración'], [
                'direccion_completa' => 'Dirección ficticia para demostración '.($indice + 1), 'zona' => 'Sopocachi', 'ciudad' => 'La Paz',
                'latitud' => -16.512 + $indice * 0.0002, 'longitud' => -68.128 + $indice * 0.0002, 'principal' => true,
                'ubicacion_jsonb' => ['origen' => 'seeder_demostracion'],
            ]);
            $clientes[] = $cliente;
        }

        return $clientes;
    }

    /** @param Collection<int, Producto> $productos */
    private function venta(string $numero, Collection $productos, User $cliente, Sucursal $sucursal, User $vendedor,
        User $repartidor, User $admin, CarbonImmutable $fecha, string $tipo, string $estadoEntrega): void
    {
        DB::transaction(function () use ($numero, $productos, $cliente, $sucursal, $vendedor, $repartidor, $admin, $fecha, $tipo, $estadoEntrega): void {
            if (Venta::query()->where('numero_venta', $numero)->exists()) {
                return;
            }
            $cancelada = $estadoEntrega === 'cancelado';
            $entregada = $estadoEntrega === 'entregado';
            $subtotal = (float) $productos->sum(fn (Producto $p): float => (float) $p->precio_venta);
            $envio = $tipo === 'delivery' ? 15.0 : 0.0;
            $venta = Venta::query()->create([
                'numero_venta' => $numero, 'cliente_id' => $cliente->id, 'usuario_vendedor_id' => $tipo === 'inmediata' ? $vendedor->id : null,
                'tipo_venta' => $tipo === 'inmediata' ? 'presencial' : 'online', 'tipo_entrega' => $tipo,
                'estado' => $cancelada ? 'cancelada' : ($entregada ? 'completada' : 'pagado'),
                'subtotal' => $subtotal, 'descuento' => 0, 'costo_envio' => $envio, 'total' => $subtotal + $envio,
                'cotizacion_envio' => ['origen' => 'seeder_demostracion', 'es_demostracion' => true],
                'fecha_venta' => $fecha, 'created_at' => $fecha, 'updated_at' => $fecha,
            ]);
            foreach ($productos as $producto) {
                $lote = LoteInventario::query()->where('codigo_lote', 'DEMO-DIV-'.$producto->codigo_producto)->lockForUpdate()->firstOrFail();
                $anterior = (int) $lote->cantidad_disponible;
                if ($anterior < 1) {
                    throw new \RuntimeException('El lote de demostración no tiene stock para '.$numero.'.');
                }
                DetalleVenta::query()->create([
                    'venta_id' => $venta->id, 'producto_id' => $producto->id, 'lote_id' => $lote->id,
                    'cantidad' => 1, 'precio_unitario' => $producto->precio_venta, 'subtotal' => $producto->precio_venta,
                    'created_at' => $fecha, 'updated_at' => $fecha,
                ]);
                $lote->update(['cantidad_disponible' => $anterior - 1]);
                MovimientoInventario::query()->create([
                    'lote_id' => $lote->id, 'usuario_id' => $admin->id, 'tipo_movimiento' => 'salida', 'cantidad' => 1,
                    'cantidad_anterior' => $anterior, 'cantidad_nueva' => $anterior - 1,
                    'motivo' => 'Venta de demostración '.$numero, 'referencia_tipo' => 'venta', 'referencia_id' => $venta->id,
                    'created_at' => $fecha, 'updated_at' => $fecha,
                ]);
                if ($cancelada) {
                    $lote->update(['cantidad_disponible' => $anterior]);
                    MovimientoInventario::query()->create([
                        'lote_id' => $lote->id, 'usuario_id' => $admin->id, 'tipo_movimiento' => 'entrada', 'cantidad' => 1,
                        'cantidad_anterior' => $anterior - 1, 'cantidad_nueva' => $anterior,
                        'motivo' => 'Devolución de demostración '.$numero, 'referencia_tipo' => 'venta', 'referencia_id' => $venta->id,
                        'created_at' => $fecha->addHours(3), 'updated_at' => $fecha->addHours(3),
                    ]);
                }
                InteraccionUsuario::query()->create([
                    'user_id' => $cliente->id, 'producto_id' => $producto->id, 'tipo_interaccion' => 'vista_producto',
                    'datos_jsonb' => ['origen' => 'seeder_demostracion', 'venta_id' => $venta->id],
                    'created_at' => $fecha->subMinutes(10), 'updated_at' => $fecha->subMinutes(10),
                ]);
                if ($entregada && ! Resena::query()->where('user_id', $cliente->id)->where('producto_id', $producto->id)->exists()) {
                    Resena::query()->create([
                        'user_id' => $cliente->id, 'producto_id' => $producto->id, 'venta_id' => $venta->id, 'calificacion' => 4,
                        'comentario' => 'Reseña de demostración: producto recibido conforme al pedido.', 'estado' => true,
                        'created_at' => $fecha->addHours(4), 'updated_at' => $fecha->addHours(4),
                    ]);
                }
            }
            Pago::query()->create([
                'venta_id' => $venta->id, 'metodo_pago' => $tipo === 'inmediata' ? 'efectivo' : 'qr',
                'referencia_pago' => $numero, 'estado' => $cancelada ? 'reembolsado' : 'completado',
                'monto' => $subtotal + $envio, 'fecha_pago' => $fecha, 'created_at' => $fecha, 'updated_at' => $fecha,
            ]);
            if ($tipo !== 'inmediata') {
                $direccion = $tipo === 'delivery' ? $cliente->direcciones()->where('nombre_referencia', 'Domicilio de demostración')->firstOrFail() : null;
                Entrega::query()->create([
                    'venta_id' => $venta->id, 'direccion_id' => $direccion?->id, 'sucursal_origen_id' => $sucursal->id,
                    'empleado_id' => $estadoEntrega === 'pendiente' ? null : ($tipo === 'delivery' ? $repartidor->id : $vendedor->id),
                    'fecha_programada' => $fecha->toDateString(), 'hora_inicio' => $fecha->format('H:i:s'), 'hora_fin' => $fecha->addMinutes(40)->format('H:i:s'),
                    'estado' => $estadoEntrega, 'notas_despacho' => 'Entrega ficticia para demostración; no realizar despacho.',
                    'destino_snapshot' => $direccion ? $direccion->only(['direccion_completa', 'zona', 'ciudad', 'latitud', 'longitud']) : null,
                    'entregado_at' => $entregada ? $fecha->addMinutes(30) : null,
                    'cancelado_at' => $cancelada ? $fecha->addMinutes(30) : null,
                    'motivo_cancelacion' => $cancelada ? 'Cancelación de demostración: cliente ausente; stock devuelto y pago reembolsado.' : null,
                    'created_at' => $fecha, 'updated_at' => $fecha,
                ]);
                Notificacion::query()->create([
                    'usuario_id' => $cliente->id, 'titulo' => 'Pedido de demostración', 'mensaje' => $numero.': '.$estadoEntrega,
                    'leido' => $entregada || $cancelada, 'clave_evento' => $numero.':entrega', 'created_at' => $fecha, 'updated_at' => $fecha,
                ]);
            }
        });
    }
}
