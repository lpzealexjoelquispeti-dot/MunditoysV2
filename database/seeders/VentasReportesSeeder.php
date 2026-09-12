<?php

namespace Database\Seeders;

use App\Models\DetalleVenta;
use App\Models\Direccion;
use App\Models\Entrega;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class VentasReportesSeeder extends Seeder
{
    /**
     * Siembra clientes con direcciones en La Paz, vendedores y choferes, y genera
     * un volumen representativo de ventas físicas y online de los juguetes Marvel
     * para alimentar todos los reportes, gráficos y mapas logísticos.
     */
    public function run(): void
    {
        $sucursal = Sucursal::query()->first();
        if (! $sucursal) {
            $this->call(MarvelCatalogSeeder::class);
            $sucursal = Sucursal::query()->first();
        }

        // 1. Asegurar Vendedor asignado a la sucursal
        $vendedor = User::query()->where('email', 'vendedor.sopocachi@munditoys.com')->first();
        if (! $vendedor) {
            $vendedor = User::query()->firstOrCreate(
                ['email' => 'vendedor.sopocachi@munditoys.com'],
                [
                    'name' => 'Alejandro',
                    'apellido_paterno' => 'Mendoza',
                    'apellido_materno' => 'Paredes',
                    'telefono' => '71548962',
                    'ci_nit' => '8492015',
                    'password' => Hash::make('password123'),
                    'tipo_usuario' => 'empleado',
                    'sucursal_id' => $sucursal->id,
                    'estado' => true,
                ]
            );
            $vendedor->syncRoles('Vendedor');
        } else {
            $vendedor->update(['sucursal_id' => $sucursal->id]);
        }

        // 2. Asegurar Chofer / Repartidor para Logística
        $chofer = User::query()->where('email', 'chofer.sopocachi@munditoys.com')->first();
        if (! $chofer) {
            $chofer = User::query()->firstOrCreate(
                ['email' => 'chofer.sopocachi@munditoys.com'],
                [
                    'name' => 'Roberto',
                    'apellido_paterno' => 'Mamani',
                    'apellido_materno' => 'Quispe',
                    'telefono' => '72034981',
                    'ci_nit' => '6791243',
                    'password' => Hash::make('password123'),
                    'tipo_usuario' => 'empleado',
                    'sucursal_id' => $sucursal->id,
                    'estado' => true,
                ]
            );
            $chofer->syncRoles('Logistica');
        }

        // 3. Crear Clientes con Direcciones Reales en La Paz
        $clientesData = [
            [
                'name' => 'Carlos',
                'apellido_paterno' => 'Fernandez',
                'apellido_materno' => 'Condori',
                'email' => 'carlos.fernandez@munditoys.com',
                'telefono' => '71234501',
                'ci_nit' => '6892341',
                'direcciones' => [
                    [
                        'nombre_referencia' => 'Mi Domicilio',
                        'direccion_completa' => 'Av. Ecuador #2140, Edif. Illimani Depto 4B',
                        'zona' => 'Sopocachi',
                        'ciudad' => 'La Paz',
                        'latitud' => -16.5135000,
                        'longitud' => -68.1276000,
                        'principal' => true,
                    ],
                ],
            ],
            [
                'name' => 'Andrea',
                'apellido_paterno' => 'Flores',
                'apellido_materno' => 'Vargas',
                'email' => 'andrea.flores@munditoys.com',
                'telefono' => '71234502',
                'ci_nit' => '7482910',
                'direcciones' => [
                    [
                        'nombre_referencia' => 'Casa Calacoto',
                        'direccion_completa' => 'Calle 18 de Calacoto #450, entre Los Sauces',
                        'zona' => 'Calacoto',
                        'ciudad' => 'La Paz',
                        'latitud' => -16.5412000,
                        'longitud' => -68.0835000,
                        'principal' => true,
                    ],
                ],
            ],
            [
                'name' => 'Rodrigo',
                'apellido_paterno' => 'Paz',
                'apellido_materno' => 'Zeballos',
                'email' => 'rodrigo.paz@munditoys.com',
                'telefono' => '71234503',
                'ci_nit' => '9182345',
                'direcciones' => [
                    [
                        'nombre_referencia' => 'Departamento',
                        'direccion_completa' => 'Calle Colombia #340, Plaza San Pedro',
                        'zona' => 'San Pedro',
                        'ciudad' => 'La Paz',
                        'latitud' => -16.4998000,
                        'longitud' => -68.1385000,
                        'principal' => true,
                    ],
                ],
            ],
            [
                'name' => 'Paola',
                'apellido_paterno' => 'Mendoza',
                'apellido_materno' => 'Rios',
                'email' => 'paola.mendoza@munditoys.com',
                'telefono' => '71234504',
                'ci_nit' => '8391024',
                'direcciones' => [
                    [
                        'nombre_referencia' => 'Mi Casa Miraflores',
                        'direccion_completa' => 'Av. Saavedra #1890 esq. Villalobos',
                        'zona' => 'Miraflores',
                        'ciudad' => 'La Paz',
                        'latitud' => -16.5028000,
                        'longitud' => -68.1215000,
                        'principal' => true,
                    ],
                ],
            ],
            [
                'name' => 'Mariana',
                'apellido_paterno' => 'Torres',
                'apellido_materno' => 'Alarcon',
                'email' => 'mariana.torres@munditoys.com',
                'telefono' => '71234505',
                'ci_nit' => '5920148',
                'direcciones' => [
                    [
                        'nombre_referencia' => 'Casa Obrajes',
                        'direccion_completa' => 'Calle 6 de Obrajes #210, Edif. Las Palmas',
                        'zona' => 'Obrajes',
                        'ciudad' => 'La Paz',
                        'latitud' => -16.5250000,
                        'longitud' => -68.1050000,
                        'principal' => true,
                    ],
                ],
            ],
            [
                'name' => 'Javier',
                'apellido_paterno' => 'Guzman',
                'apellido_materno' => 'Suarez',
                'email' => 'javier.guzman@munditoys.com',
                'telefono' => '71234506',
                'ci_nit' => '9401825',
                'direcciones' => [
                    [
                        'nombre_referencia' => 'Casa Achumani',
                        'direccion_completa' => 'Calle 22 de Achumani #80, Condominio El Sol',
                        'zona' => 'Achumani',
                        'ciudad' => 'La Paz',
                        'latitud' => -16.5350000,
                        'longitud' => -68.0700000,
                        'principal' => true,
                    ],
                ],
            ],
        ];

        $clientesModelos = [];
        foreach ($clientesData as $cd) {
            $cliente = User::query()->firstOrCreate(
                ['email' => $cd['email']],
                [
                    'name' => $cd['name'],
                    'apellido_paterno' => $cd['apellido_paterno'],
                    'apellido_materno' => $cd['apellido_materno'],
                    'telefono' => $cd['telefono'],
                    'ci_nit' => $cd['ci_nit'],
                    'password' => Hash::make('password123'),
                    'tipo_usuario' => 'cliente',
                    'estado' => true,
                ]
            );
            $cliente->syncRoles('Cliente');

            foreach ($cd['direcciones'] as $dirData) {
                Direccion::query()->firstOrCreate(
                    [
                        'user_id' => $cliente->id,
                        'nombre_referencia' => $dirData['nombre_referencia'],
                    ],
                    $dirData
                );
            }

            $clientesModelos[] = $cliente;
        }

        // 4. Obtener los 6 Juguetes Marvel con sus inventarios y lotes
        $productosMarvel = Producto::query()
            ->where('estado', 'activo')
            ->with(['inventarios.lotes' => fn ($q) => $q->where('estado', 'activo')->where('cantidad_disponible', '>', 0)])
            ->get();

        if ($productosMarvel->isEmpty()) {
            return;
        }

        $correlativoVenta = 100;

        // 5. GENERAR VENTAS FÍSICAS POS (MOSTRADOR) ~ 36 Ventas
        // Distribuidas en los últimos 28 días
        $metodosPagoPos = ['efectivo', 'qr', 'tarjeta'];

        for ($diaAtras = 28; $diaAtras >= 0; $diaAtras--) {
            // Entre 1 y 2 ventas diarias en mostrador
            $numVentasDia = ($diaAtras % 3 === 0) ? 2 : 1;

            for ($k = 0; $k < $numVentasDia; $k++) {
                $fechaVenta = now()->subDays($diaAtras)->setTime(rand(10, 19), rand(10, 55));
                $fechaPrefijo = $fechaVenta->format('Ymd');
                $correlativoVenta++;
                $numeroVenta = sprintf('VTA-POS-%s-%04d', $fechaPrefijo, $correlativoVenta);

                if (Venta::query()->where('numero_venta', $numeroVenta)->exists()) {
                    continue;
                }

                // Elegir 1 o 2 juguetes para esta venta
                $itemsVenta = $productosMarvel->random(rand(1, 2));
                $subtotal = 0.0;
                $detallesParaCrear = [];

                foreach ($itemsVenta as $prod) {
                    $cant = rand(1, 2);
                    $pu = (float) $prod->precio_venta;
                    $st = round($cant * $pu, 2);
                    $subtotal += $st;

                    // Obtener lote disponible para trazabilidad FIFO
                    $primerInv = $prod->inventarios->first();
                    $lote = $primerInv?->lotes->first();

                    $detallesParaCrear[] = [
                        'producto_id' => $prod->id,
                        'lote_id' => $lote?->id,
                        'cantidad' => $cant,
                        'precio_unitario' => $pu,
                        'subtotal' => $st,
                    ];

                    if ($lote && $lote->cantidad_disponible >= $cant) {
                        $lote->decrement('cantidad_disponible', $cant);
                    }
                }

                $total = $subtotal;
                $clienteElegido = rand(0, 1) ? $clientesModelos[array_rand($clientesModelos)] : null;

                $venta = Venta::query()->create([
                    'numero_venta' => $numeroVenta,
                    'cliente_id' => $clienteElegido?->id,
                    'usuario_vendedor_id' => $vendedor->id,
                    'tipo_venta' => 'presencial',
                    'tipo_entrega' => 'inmediata',
                    'estado' => 'completada',
                    'subtotal' => $subtotal,
                    'descuento' => 0.00,
                    'total' => $total,
                    'fecha_venta' => $fechaVenta,
                    'created_at' => $fechaVenta,
                    'updated_at' => $fechaVenta,
                ]);

                foreach ($detallesParaCrear as $det) {
                    DetalleVenta::query()->create(array_merge($det, ['venta_id' => $venta->id]));
                }

                $metodo = $metodosPagoPos[array_rand($metodosPagoPos)];
                Pago::query()->create([
                    'venta_id' => $venta->id,
                    'metodo_pago' => $metodo,
                    'referencia_pago' => $metodo === 'efectivo' ? null : 'POS-'.Str::upper(Str::random(8)),
                    'estado' => 'completado',
                    'monto' => $total,
                    'fecha_pago' => $fechaVenta,
                    'created_at' => $fechaVenta,
                    'updated_at' => $fechaVenta,
                ]);
            }
        }

        // 6. GENERAR VENTAS ONLINE - RECOJO EN TIENDA ~ 12 Ventas
        // Algunas completadas y otras pendientes listas para escanear QR
        for ($i = 1; $i <= 12; $i++) {
            $esPendiente = ($i > 8); // Los últimos 4 quedan pendientes para pruebas de escaneo QR
            $fechaVenta = $esPendiente ? now()->subHours(rand(1, 6)) : now()->subDays(rand(2, 20))->setTime(rand(9, 18), rand(10, 50));
            $fechaPrefijo = $fechaVenta->format('Ymd');
            $correlativoVenta++;
            $numeroVenta = sprintf('VTA-REC-%s-%04d', $fechaPrefijo, $correlativoVenta);

            if (Venta::query()->where('numero_venta', $numeroVenta)->exists()) {
                continue;
            }

            $prod = $productosMarvel->random();
            $cant = 1;
            $pu = (float) $prod->precio_venta;
            $total = $pu;

            $primerInv = $prod->inventarios->first();
            $lote = $primerInv?->lotes->first();

            $cliente = $clientesModelos[array_rand($clientesModelos)];

            $venta = Venta::query()->create([
                'numero_venta' => $numeroVenta,
                'cliente_id' => $cliente->id,
                'usuario_vendedor_id' => null,
                'tipo_venta' => 'online',
                'tipo_entrega' => 'recojo_tienda',
                'estado' => $esPendiente ? 'pagado' : 'completada',
                'subtotal' => $total,
                'descuento' => 0.00,
                'total' => $total,
                'fecha_venta' => $fechaVenta,
                'created_at' => $fechaVenta,
                'updated_at' => $fechaVenta,
            ]);

            DetalleVenta::query()->create([
                'venta_id' => $venta->id,
                'producto_id' => $prod->id,
                'lote_id' => $lote?->id,
                'cantidad' => $cant,
                'precio_unitario' => $pu,
                'subtotal' => $total,
            ]);

            if ($lote && $lote->cantidad_disponible >= $cant) {
                $lote->decrement('cantidad_disponible', $cant);
            }

            Pago::query()->create([
                'venta_id' => $venta->id,
                'metodo_pago' => 'stripe',
                'referencia_pago' => 'ch_'.Str::random(24),
                'stripe_payment_id' => 'pi_'.Str::random(24),
                'estado' => 'completado',
                'monto' => $total,
                'fecha_pago' => $fechaVenta,
                'created_at' => $fechaVenta,
                'updated_at' => $fechaVenta,
            ]);

            Entrega::query()->create([
                'venta_id' => $venta->id,
                'direccion_id' => null,
                'empleado_id' => $esPendiente ? null : $vendedor->id,
                'sucursal_origen_id' => $sucursal->id,
                'fecha_programada' => $fechaVenta->toDateString(),
                'hora_inicio' => null,
                'hora_fin' => null,
                'estado' => $esPendiente ? 'pendiente' : 'entregado',
                'entregado_at' => $esPendiente ? null : $fechaVenta->addMinutes(rand(30, 180)),
                'created_at' => $fechaVenta,
                'updated_at' => $fechaVenta,
            ]);
        }

        // 7. GENERAR VENTAS ONLINE - DELIVERY (LOGÍSTICA) ~ 12 Ventas
        // Turnos matemáticos de 90 minutos (12:00, 13:30, 15:00, 16:30, 18:00)
        $turnos = [
            ['inicio' => '12:00:00', 'fin' => '12:40:00'],
            ['inicio' => '13:30:00', 'fin' => '14:10:00'],
            ['inicio' => '15:00:00', 'fin' => '15:40:00'],
            ['inicio' => '16:30:00', 'fin' => '17:10:00'],
            ['inicio' => '18:00:00', 'fin' => '18:40:00'],
        ];

        // 4 entregas para HOY (para alimentar el mapa y tablero de /admin/logistica)
        for ($t = 0; $t < 4; $t++) {
            $correlativoVenta++;
            $fechaPrefijo = now()->format('Ymd');
            $numeroVenta = sprintf('VTA-DEL-%s-%04d', $fechaPrefijo, $correlativoVenta);

            if (Venta::query()->where('numero_venta', $numeroVenta)->exists()) {
                continue;
            }

            $prod = $productosMarvel->random();
            $pu = (float) $prod->precio_venta;
            $total = $pu;

            $primerInv = $prod->inventarios->first();
            $lote = $primerInv?->lotes->first();
            $cliente = $clientesModelos[$t % count($clientesModelos)];
            $direccion = $cliente->direcciones()->first();

            $venta = Venta::query()->create([
                'numero_venta' => $numeroVenta,
                'cliente_id' => $cliente->id,
                'usuario_vendedor_id' => null,
                'tipo_venta' => 'online',
                'tipo_entrega' => 'delivery',
                'estado' => 'pagado',
                'subtotal' => $total,
                'descuento' => 0.00,
                'total' => $total,
                'fecha_venta' => now(),
            ]);

            DetalleVenta::query()->create([
                'venta_id' => $venta->id,
                'producto_id' => $prod->id,
                'lote_id' => $lote?->id,
                'cantidad' => 1,
                'precio_unitario' => $pu,
                'subtotal' => $total,
            ]);

            if ($lote && $lote->cantidad_disponible >= 1) {
                $lote->decrement('cantidad_disponible', 1);
            }

            Pago::query()->create([
                'venta_id' => $venta->id,
                'metodo_pago' => 'stripe',
                'referencia_pago' => 'ch_'.Str::random(24),
                'stripe_payment_id' => 'pi_'.Str::random(24),
                'estado' => 'completado',
                'monto' => $total,
                'fecha_pago' => now(),
            ]);

            $estadoHoy = ($t === 0) ? 'en_camino' : 'pendiente';
            Entrega::query()->create([
                'venta_id' => $venta->id,
                'direccion_id' => $direccion?->id,
                'empleado_id' => $chofer->id,
                'sucursal_origen_id' => $sucursal->id,
                'fecha_programada' => now()->toDateString(),
                'hora_inicio' => $turnos[$t]['inicio'],
                'hora_fin' => $turnos[$t]['fin'],
                'estado' => $estadoHoy,
                'notas_despacho' => 'Entregar en portería o recepción con documento de identidad.',
            ]);
        }

        // 8 entregas pasadas (6 entregadas y 2 canceladas con motivo)
        for ($p = 1; $p <= 8; $p++) {
            $diasAtras = rand(2, 25);
            $fechaPasada = now()->subDays($diasAtras);
            $correlativoVenta++;
            $numeroVenta = sprintf('VTA-DEL-%s-%04d', $fechaPasada->format('Ymd'), $correlativoVenta);

            if (Venta::query()->where('numero_venta', $numeroVenta)->exists()) {
                continue;
            }

            $prod = $productosMarvel->random();
            $pu = (float) $prod->precio_venta;
            $total = $pu;

            $primerInv = $prod->inventarios->first();
            $lote = $primerInv?->lotes->first();
            $cliente = $clientesModelos[$p % count($clientesModelos)];
            $direccion = $cliente->direcciones()->first();

            $esCancelada = ($p > 6);

            $venta = Venta::query()->create([
                'numero_venta' => $numeroVenta,
                'cliente_id' => $cliente->id,
                'usuario_vendedor_id' => null,
                'tipo_venta' => 'online',
                'tipo_entrega' => 'delivery',
                'estado' => $esCancelada ? 'cancelada' : 'completada',
                'subtotal' => $total,
                'descuento' => 0.00,
                'total' => $total,
                'fecha_venta' => $fechaPasada,
                'created_at' => $fechaPasada,
                'updated_at' => $fechaPasada,
            ]);

            DetalleVenta::query()->create([
                'venta_id' => $venta->id,
                'producto_id' => $prod->id,
                'lote_id' => $lote?->id,
                'cantidad' => 1,
                'precio_unitario' => $pu,
                'subtotal' => $total,
            ]);

            Pago::query()->create([
                'venta_id' => $venta->id,
                'metodo_pago' => 'stripe',
                'referencia_pago' => 'ch_'.Str::random(24),
                'stripe_payment_id' => 'pi_'.Str::random(24),
                'estado' => 'completado',
                'monto' => $total,
                'fecha_pago' => $fechaPasada,
                'created_at' => $fechaPasada,
                'updated_at' => $fechaPasada,
            ]);

            $turno = $turnos[array_rand($turnos)];

            Entrega::query()->create([
                'venta_id' => $venta->id,
                'direccion_id' => $direccion?->id,
                'empleado_id' => $chofer->id,
                'sucursal_origen_id' => $sucursal->id,
                'fecha_programada' => $fechaPasada->toDateString(),
                'hora_inicio' => $turno['inicio'],
                'hora_fin' => $turno['fin'],
                'estado' => $esCancelada ? 'cancelado' : 'entregado',
                'motivo_cancelacion' => $esCancelada ? 'Cliente no se encontraba en el domicilio en la hora acordada.' : null,
                'cancelado_at' => $esCancelada ? $fechaPasada->copy()->addMinutes(45) : null,
                'entregado_at' => $esCancelada ? null : $fechaPasada->copy()->addMinutes(35),
                'created_at' => $fechaPasada,
                'updated_at' => $fechaPasada,
            ]);
        }
    }
}
