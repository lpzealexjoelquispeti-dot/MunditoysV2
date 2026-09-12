<?php

use App\Models\Categoria;
use App\Models\DetalleVenta;
use App\Models\Entrega;
use App\Models\Marca;
use App\Models\Notificacion;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use App\Services\ResenaCompraService;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('notificar resenas tras entrega crea notificaciones con clave_evento correctamente', function () {
    $this->seed([PermisosSeeder::class, RolesSeeder::class]);

    $cliente = User::factory()->create();
    $cliente->assignRole('Cliente');

    $sucursal = Sucursal::create([
        'nombre' => 'Sucursal Central',
        'ciudad' => 'La Paz',
        'direccion' => 'Av. 16 de Julio #1490',
        'telefono' => '22443322',
        'activo' => true,
    ]);

    $categoria = Categoria::create(['nombre' => 'Figuras', 'activo' => true]);
    $marca = Marca::create(['nombre' => 'Marvel', 'activo' => true]);
    $proveedor = Proveedor::create([
        'empresa' => 'Marvel Toys Corp',
        'nombre_contacto' => 'Distribuidor',
        'telefono' => '71122334',
        'activo' => true,
    ]);

    $producto = Producto::create([
        'categoria_id' => $categoria->id,
        'marca_id' => $marca->id,
        'proveedor_id' => $proveedor->id,
        'codigo_producto' => 'PRD-COLOSSUS-001',
        'codigo_barra' => 'FIG-COLOSSUS-001',
        'codigo_qr' => 'FIG-COLOSSUS-001',
        'nombre' => 'Colossus Marvel',
        'precio_unitario' => 380.00,
        'precio_venta' => 380.00,
        'stock_minimo' => 2,
        'estado' => 'activo',
    ]);

    $venta = Venta::create([
        'numero_venta' => 'VTA-TEST-0001',
        'cliente_id' => $cliente->id,
        'sucursal_id' => $sucursal->id,
        'total' => 380.00,
        'subtotal' => 380.00,
        'impuesto' => 0.00,
        'tipo_comprobante' => 'factura',
        'metodo_pago' => 'efectivo',
        'tipo_entrega' => 'recojo_tienda',
        'estado' => 'completada',
        'fecha_venta' => now(),
    ]);

    DetalleVenta::create([
        'venta_id' => $venta->id,
        'producto_id' => $producto->id,
        'cantidad' => 1,
        'precio_unitario' => 380.00,
        'subtotal' => 380.00,
    ]);

    Entrega::create([
        'venta_id' => $venta->id,
        'sucursal_origen_id' => $sucursal->id,
        'estado' => 'entregado',
        'entregado_at' => now(),
    ]);

    Pago::create([
        'venta_id' => $venta->id,
        'monto' => 380.00,
        'metodo_pago' => 'efectivo',
        'estado' => 'completado',
        'referencia_pago' => 'PAG-0001',
        'fecha_pago' => now(),
    ]);

    app(ResenaCompraService::class)->notificar($venta);

    $notificacion = Notificacion::query()
        ->where('usuario_id', $cliente->id)
        ->where('clave_evento', "resena:{$venta->id}:{$producto->id}")
        ->first();

    expect($notificacion)->not->toBeNull()
        ->and($notificacion->producto_id)->toBe($producto->id)
        ->and($notificacion->clave_evento)->toBe("resena:{$venta->id}:{$producto->id}");
});
