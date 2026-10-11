<?php

namespace Database\Seeders;

use App\Models\DetalleVenta;
use App\Models\Entrega;
use App\Models\LoteInventario;
use App\Models\MovimientoInventario;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\User;
use App\Models\Venta;
use Carbon\CarbonImmutable;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class VendedorPresentacionSeeder extends Seeder
{
    use WithoutModelEvents;

    public const EMAIL_VENDEDOR = 'vendedor.presentacion@example.test';

    public const EMAIL_CLIENTE = 'alexjoelquispeticona41@gmail.com';

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Este seeder solo puede ejecutarse en local o testing.');
        }

        $cliente = User::query()->where('email', self::EMAIL_CLIENTE)->where('estado', true)->firstOrFail();
        $claveNueva = null;

        DB::transaction(function () use ($cliente, &$claveNueva): void {
            $this->call(CatalogoDiversoSeeder::class);
            $lotes = LoteInventario::query()
                ->whereBetween('codigo_lote', ['DEMO-DIV-MT-DIV-001', 'DEMO-DIV-MT-DIV-024'])
                ->where('estado', 'activo')
                ->whereHas('inventario', fn (Builder $query): Builder => $query->whereNull('deleted_at')
                    ->whereHas('producto', fn (Builder $producto): Builder => $producto->whereNull('deleted_at')->where('estado', 'activo'))
                    ->whereHas('almacen', fn (Builder $almacen): Builder => $almacen->whereNull('deleted_at')->where('estado', true)
                        ->whereHas('sucursal', fn (Builder $sucursal): Builder => $sucursal->whereNull('deleted_at')->where('estado', true))))
                ->with('inventario.producto', 'inventario.almacen.sucursal')
                ->orderBy('codigo_lote')->get();
            if ($lotes->count() < 2) {
                throw new RuntimeException('Se requieren al menos dos juguetes activos del catálogo de demostración.');
            }

            $sucursal = $lotes->first()->inventario->almacen->sucursal;
            $lotes = $lotes->filter(fn (LoteInventario $lote): bool => $lote->inventario->almacen->sucursal_id === $sucursal->id)->values();
            if ($lotes->count() < 2) {
                throw new RuntimeException('La sucursal necesita dos juguetes del catálogo de demostración.');
            }

            $vendedor = User::query()->where('email', self::EMAIL_VENDEDOR)->first();
            if (! $vendedor) {
                $claveNueva = Str::random(20);
                $vendedor = User::query()->create([
                    'name' => 'Diego', 'apellido_paterno' => 'Vendedor', 'apellido_materno' => 'Demo',
                    'email' => self::EMAIL_VENDEDOR, 'password' => $claveNueva,
                    'estado' => true, 'tipo_usuario' => 'empleado', 'sucursal_id' => $sucursal->id,
                ]);
                $vendedor->forceFill(['email_verified_at' => now(), 'invitacion_pendiente' => false])->save();
                $vendedor->assignRole('Vendedor');
            } elseif (! $vendedor->estado || $vendedor->invitacion_pendiente
                || $vendedor->sucursal_id !== $sucursal->id || $vendedor->getRoleNames()->all() !== ['Vendedor']) {
                throw new RuntimeException('La cuenta de demostración existente no coincide con el vendedor esperado.');
            }

            $ahora = CarbonImmutable::now();
            foreach (range(1, 20) as $indice) {
                $fecha = $indice <= 16
                    ? $ahora->subDays(17 - $indice)->setTime(14, 0)
                    : $ahora->subMinutes((21 - $indice) * 5);
                $this->crearVenta(sprintf('DEMO-PRES-POS-%02d', $indice), $lotes, $indice,
                    $cliente, $vendedor, $fecha, false, true);
            }
            foreach (range(1, 6) as $indice) {
                $fecha = $indice <= 4 ? $ahora->subDays(8 - $indice)->setTime(11, 0) : $ahora->subMinutes((7 - $indice) * 5);
                $this->crearVenta(sprintf('DEMO-PRES-RECOJO-%02d', $indice), $lotes, $indice + 20,
                    $cliente, $vendedor, $fecha, true, $indice <= 4);
            }
        });

        $this->command?->info('Vendedor: '.self::EMAIL_VENDEDOR);
        if ($claveNueva !== null) {
            $this->command?->info('Contraseña inicial: '.$claveNueva);
        } else {
            $this->command?->info('Cuenta existente: se conservó su contraseña.');
        }
        $this->command?->info('20 ventas presenciales y 6 recojos para '.self::EMAIL_CLIENTE.'; sin duplicar registros.');
    }

    /** @param Collection<int, LoteInventario> $lotes */
    private function crearVenta(string $numero, Collection $lotes, int $indice, User $cliente, User $vendedor,
        CarbonImmutable $fecha, bool $recojo, bool $entregada): void
    {
        if (Venta::query()->where('numero_venta', $numero)->exists()) {
            return;
        }

        $seleccion = [$lotes[($indice - 1) % $lotes->count()], $lotes[$indice % $lotes->count()]];
        $cantidades = [$indice % 3 === 0 ? 2 : 1, 1];
        $total = 0.0;
        foreach ($seleccion as $posicion => $lote) {
            $total += round((float) $lote->inventario->producto->precio_venta * $cantidades[$posicion], 2);
        }
        $venta = Venta::query()->create([
            'numero_venta' => $numero, 'cliente_id' => $cliente->id,
            'usuario_vendedor_id' => $recojo ? null : $vendedor->id,
            'tipo_venta' => $recojo ? 'online' : 'presencial', 'tipo_entrega' => $recojo ? 'recojo_tienda' : 'inmediata',
            'estado' => $entregada ? 'completada' : 'pagada', 'subtotal' => $total, 'descuento' => 0,
            'costo_envio' => 0, 'total' => $total,
            'cotizacion_envio' => ['origen' => 'seeder_presentacion_vendedor', 'es_demostracion' => true],
            'fecha_venta' => $fecha, 'created_at' => $fecha, 'updated_at' => $fecha,
        ]);

        foreach ($seleccion as $posicion => $seleccionado) {
            $lote = LoteInventario::query()->lockForUpdate()->findOrFail($seleccionado->id);
            $cantidad = $cantidades[$posicion];
            $anterior = $lote->cantidad_disponible;
            if ($anterior < $cantidad) {
                throw new RuntimeException('Stock de demostración insuficiente para '.$numero.'.');
            }
            $precio = $seleccionado->inventario->producto->precio_venta;
            DetalleVenta::query()->create([
                'venta_id' => $venta->id, 'producto_id' => $seleccionado->inventario->producto_id, 'lote_id' => $lote->id,
                'cantidad' => $cantidad, 'precio_unitario' => $precio, 'subtotal' => round((float) $precio * $cantidad, 2),
                'created_at' => $fecha, 'updated_at' => $fecha,
            ]);
            $lote->update(['cantidad_disponible' => $anterior - $cantidad]);
            MovimientoInventario::query()->create([
                'lote_id' => $lote->id, 'usuario_id' => $vendedor->id, 'tipo_movimiento' => 'salida',
                'cantidad' => $cantidad, 'cantidad_anterior' => $anterior, 'cantidad_nueva' => $anterior - $cantidad,
                'motivo' => 'Venta de demostración '.$numero, 'referencia_tipo' => 'venta', 'referencia_id' => $venta->id,
                'created_at' => $fecha, 'updated_at' => $fecha,
            ]);
        }
        Pago::query()->create([
            'venta_id' => $venta->id, 'metodo_pago' => $recojo || $indice % 2 === 0 ? 'qr' : 'efectivo',
            'referencia_pago' => $numero, 'estado' => 'completado', 'monto' => $total,
            'fecha_pago' => $fecha, 'created_at' => $fecha, 'updated_at' => $fecha,
        ]);
        if ($recojo) {
            Entrega::query()->create([
                'venta_id' => $venta->id, 'sucursal_origen_id' => $vendedor->sucursal_id,
                'empleado_id' => $entregada ? $vendedor->id : null,
                'fecha_programada' => $entregada ? $fecha->toDateString() : today()->toDateString(),
                'hora_inicio' => $entregada ? $fecha->format('H:i:s') : '09:00:00',
                'hora_fin' => $entregada ? $fecha->addMinutes(30)->format('H:i:s') : '19:00:00',
                'estado' => $entregada ? 'entregado' : 'pendiente',
                'entregado_at' => $entregada ? $fecha->addMinutes(30) : null,
                'notas_despacho' => 'Pedido de demostración para presentar el flujo de recojo en sucursal.',
                'created_at' => $fecha, 'updated_at' => $fecha,
            ]);
            Notificacion::query()->create([
                'usuario_id' => $cliente->id, 'titulo' => 'Recojo de demostración',
                'mensaje' => $numero.($entregada ? ': entregado en sucursal.' : ': listo para probar el recojo con tu QR.'),
                'leido' => $entregada, 'clave_evento' => $numero.':recojo', 'created_at' => $fecha, 'updated_at' => $fecha,
            ]);
        }
    }
}
