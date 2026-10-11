<?php

namespace Database\Seeders;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\LoteInventario;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MarvelCatalogSeeder extends Seeder
{
    /**
     * Registra los 5 juguetes Marvel solicitados (más el Capitán América existente),
     * vinculándolos a inventarios y lotes FIFO con precios estrictamente entre Bs. 300 y Bs. 500.
     */
    public function run(): void
    {
        // 1. Asegurar Categoría 'Acción y aventuras'
        $categoria = Categoria::query()->firstOrCreate(
            ['nombre' => 'Acción y aventuras'],
            ['estado' => true]
        );

        // 2. Asegurar Marca 'Hasbro'
        $marca = Marca::query()->firstOrCreate(
            ['nombre' => 'Hasbro'],
            ['estado' => true]
        );

        // 3. Asegurar Proveedor oficial 'Hasbro'
        $proveedor = Proveedor::query()->firstOrCreate(
            ['empresa' => 'Hasbro'],
            [
                'nombre_contacto' => 'Distribuidora Oficial Hasbro Bolivia',
                'telefono' => '76222802',
                'email' => 'ventas.hasbro@munditoys.com',
                'direccion' => 'Av. Arce, Edificio Multicentro Torre B',
                'estado' => true,
            ]
        );

        // 4. Asegurar Sucursal Principal y Almacén
        $sucursal = Sucursal::query()->firstOrCreate(
            ['nombre' => 'Munditoys Sopocachi'],
            [
                'direccion' => 'Plaza Abaroa esq. Sánchez Lima #2230',
                'zona' => 'Sopocachi',
                'ciudad' => 'La Paz',
                'latitud' => -16.5134811,
                'longitud' => -68.1275511,
                'telefono' => '76222802',
                'estado' => true,
            ]
        );

        $almacen = Almacen::query()->firstOrCreate(
            [
                'sucursal_id' => $sucursal->id,
                'nombre' => 'Juguetron 2',
            ],
            [
                'tipo' => 'principal',
                'estado' => true,
            ]
        );

        // 5. Usuario responsable del registro de lotes (Admin)
        $admin = User::query()->where('email', 'admin@munditoys.com')->first()
            ?? User::role('Administrador')->first()
            ?? User::first();

        $adminId = $admin?->id ?? 1;

        // 6. Catálogo de los 5 juguetes Marvel solicitados + Capitán América
        // Precios estrictamente entre Bs. 300 y Bs. 500
        $juguetes = [
            [
                'codigo_producto' => 'MT-COLOSSUS-001',
                'codigo_qr' => 'MTQR-COLOSSUS-001',
                'nombre' => 'Colossus Marvel Legends',
                'descripcion' => 'Figura de acción articulada de Colossus, personaje de los X-Men, de la línea Marvel Legends de Hasbro. Coleccionable con apariencia metálica y traje rojo con amarillo.',
                'precio_compra_referencia' => 265.00,
                'precio_venta' => 380.00,
                'edad_recomendada' => '9-12',
                'peso' => 0.65,
                'dimensiones' => '15 x 30 x 8 cm',
            ],
            [
                'codigo_producto' => 'MT-APOCALYPSE-002',
                'codigo_qr' => 'MTQR-APOCALYPSE-002',
                'nombre' => 'Apocalypse Marvel Legends',
                'descripcion' => 'Figura de acción articulada de Apocalypse (Apocalipsis), personaje de los X-Men, de la línea Marvel Legends de Hasbro. Coleccionable con armadura azul y morada.',
                'precio_compra_referencia' => 320.00,
                'precio_venta' => 460.00,
                'edad_recomendada' => '13+',
                'peso' => 0.95,
                'dimensiones' => '20 x 35 x 12 cm',
            ],
            [
                'codigo_producto' => 'MT-JUGGERNAUT-003',
                'codigo_qr' => 'MTQR-JUGGERNAUT-003',
                'nombre' => 'Juggernaut Marvel Legends',
                'descripcion' => 'Figura de acción articulada de Juggernaut, personaje de los X-Men, de la línea Marvel Legends de Hasbro. Coleccionable con casco y traje de tonos rojos y marrones.',
                'precio_compra_referencia' => 305.00,
                'precio_venta' => 440.00,
                'edad_recomendada' => '9-12',
                'peso' => 0.90,
                'dimensiones' => '18 x 32 x 12 cm',
            ],
            [
                'codigo_producto' => 'MT-THANOS-004',
                'codigo_qr' => 'MTQR-THANOS-004',
                'nombre' => 'Thanos Marvel Legends',
                'descripcion' => 'Figura de acción articulada de Thanos de la línea Marvel Legends de Hasbro, inspirada en The Infinity Gauntlet. Coleccionable con armadura azul y dorada y Guantelete del Infinito.',
                'precio_compra_referencia' => 345.00,
                'precio_venta' => 495.00,
                'edad_recomendada' => '13+',
                'peso' => 0.85,
                'dimensiones' => '18 x 33 x 10 cm',
            ],
            [
                'codigo_producto' => 'MT-IRONMAN-005',
                'codigo_qr' => 'MTQR-IRONMAN-005',
                'nombre' => 'Iron Man Marvel Legends',
                'descripcion' => 'Figura de acción articulada de Iron Man de la línea Marvel Legends de Hasbro. Coleccionable de Tony Stark con armadura roja y dorada.',
                'precio_compra_referencia' => 245.00,
                'precio_venta' => 350.00,
                'edad_recomendada' => '6-8',
                'peso' => 0.45,
                'dimensiones' => '12 x 28 x 6 cm',
            ],
        ];

        // Identificar también el Capitán América existente como parte de la línea Marvel Legends.
        $capitan = Producto::query()->where('nombre', 'like', '%Capitan America%')->first();
        if ($capitan) {
            $capitan->update([
                'nombre' => 'Capitán América Marvel Legends',
                'marca_id' => $marca->id,
                'descripcion' => 'Figura de acción articulada de Capitán América de la línea Marvel Legends de Hasbro, con su característico escudo.',
            ]);
        }

        // 7. Crear los productos y sus lotes de inventario FIFO
        foreach ($juguetes as $datosProd) {
            $producto = Producto::query()->firstOrCreate(
                ['codigo_producto' => $datosProd['codigo_producto']],
                array_merge($datosProd, [
                    'categoria_id' => $categoria->id,
                    'marca_id' => $marca->id,
                    'estado' => 'activo',
                ])
            );

            $producto->update([
                'nombre' => $datosProd['nombre'], 'descripcion' => $datosProd['descripcion'], 'marca_id' => $marca->id,
            ]);

            // Asegurar registro de inventario en el almacén de la sucursal
            $inventario = Inventario::query()->firstOrCreate(
                [
                    'producto_id' => $producto->id,
                    'almacen_id' => $almacen->id,
                ],
                ['stock_minimo' => 5]
            );

            // Generar 2 lotes escalonados FIFO para el producto
            $codigoBase = strtoupper(str_replace(['MT-', '-001', '-002', '-003', '-004', '-005'], '', $datosProd['codigo_producto']));
            $precioVenta = (float) $datosProd['precio_venta'];

            // Lote 1: Adquirido hace 30 días
            $costo1 = round($precioVenta * 0.70, 2);
            $margen1 = round((($precioVenta - $costo1) / $costo1) * 100, 2);
            $codigoLote1 = "MTL-{$codigoBase}-L01";

            LoteInventario::query()->firstOrCreate(
                [
                    'inventario_id' => $inventario->id,
                    'codigo_lote' => $codigoLote1,
                ],
                [
                    'proveedor_id' => $proveedor->id,
                    'cantidad_inicial' => 100,
                    'cantidad_disponible' => 100,
                    'costo_unitario' => $costo1,
                    'precio_venta' => $precioVenta,
                    'margen_porcentaje' => $margen1,
                    'fecha_ingreso' => now()->subDays(30)->toDateString(),
                    'clave_ingreso' => (string) Str::uuid(),
                    'registrado_por' => $adminId,
                    'estado' => 'activo',
                ]
            );

            // Lote 2: Adquirido hace 10 días
            $costo2 = round($precioVenta * 0.72, 2);
            $margen2 = round((($precioVenta - $costo2) / $costo2) * 100, 2);
            $codigoLote2 = "MTL-{$codigoBase}-L02";

            LoteInventario::query()->firstOrCreate(
                [
                    'inventario_id' => $inventario->id,
                    'codigo_lote' => $codigoLote2,
                ],
                [
                    'proveedor_id' => $proveedor->id,
                    'cantidad_inicial' => 100,
                    'cantidad_disponible' => 100,
                    'costo_unitario' => $costo2,
                    'precio_venta' => $precioVenta,
                    'margen_porcentaje' => $margen2,
                    'fecha_ingreso' => now()->subDays(10)->toDateString(),
                    'clave_ingreso' => (string) Str::uuid(),
                    'registrado_por' => $adminId,
                    'estado' => 'activo',
                ]
            );
        }
        $this->call(ImagenesCatalogoSeeder::class);
        Cache::forget('asistente:vectores-sincronizados');
    }
}
