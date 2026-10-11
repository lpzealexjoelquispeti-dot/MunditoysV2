<?php

namespace Database\Seeders;

use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Inventario;
use App\Models\LoteInventario;
use App\Models\Marca;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\ProductoProveedor;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class CatalogoDiversoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }
        if (! Role::query()->where('name', 'Administrador')->exists()) {
            $this->call([PermisosSeeder::class, RolesSeeder::class]);
        }
        $admin = User::role('Administrador')->first();
        if (! $admin) {
            $this->call(AdminSeeder::class);
            $admin = User::role('Administrador')->firstOrFail();
        }

        DB::transaction(function () use ($admin): void {
            $sucursal = Sucursal::query()->where('estado', true)->first() ?? Sucursal::query()->create([
                'nombre' => 'Munditoys Sopocachi', 'direccion' => 'Plaza Abaroa, Sopocachi',
                'zona' => 'Sopocachi', 'ciudad' => 'La Paz', 'latitud' => -16.5134811, 'longitud' => -68.1275511,
                'telefono' => '76222802', 'estado' => true,
            ]);
            $almacen = Almacen::query()->where('sucursal_id', $sucursal->id)->where('estado', true)->first()
                ?? Almacen::query()->create(['sucursal_id' => $sucursal->id, 'nombre' => 'Catálogo diverso', 'tipo' => 'principal', 'estado' => true]);
            $proveedor = Proveedor::query()->firstOrCreate(['empresa' => 'Distribuidora de demostración'], [
                'nombre_contacto' => 'Catálogo de prueba', 'email' => 'proveedor.demo@example.test', 'estado' => true,
            ]);

            foreach ($this->catalogo() as $indice => [$nombre, $categoriaNombre, $marcaNombre, $edad, $precio, $descripcion]) {
                $codigo = sprintf('MT-DIV-%03d', $indice + 1);
                $categoria = Categoria::query()->firstOrCreate(['nombre' => $categoriaNombre], ['estado' => true]);
                $marca = Marca::query()->firstOrCreate(['nombre' => $marcaNombre], ['estado' => true]);
                $costo = round($precio * 0.6, 2);
                $producto = Producto::query()->firstOrCreate(['codigo_producto' => $codigo], [
                    'codigo_qr' => 'QR-'.$codigo, 'nombre' => $nombre, 'descripcion' => $descripcion,
                    'categoria_id' => $categoria->id, 'marca_id' => $marca->id, 'edad_recomendada' => $edad,
                    'precio_venta' => $precio, 'precio_compra_referencia' => $costo, 'estado' => 'activo',
                ]);
                $inventario = Inventario::query()->firstOrCreate(['producto_id' => $producto->id, 'almacen_id' => $almacen->id], ['stock_minimo' => 5]);
                ProductoProveedor::query()->firstOrCreate(['producto_id' => $producto->id, 'proveedor_id' => $proveedor->id], [
                    'codigo_proveedor' => 'DEMO-'.$codigo, 'costo_compra' => $costo, 'tiempo_entrega_dias' => 7,
                ]);
                $cantidad = match ($indice) {
                    24 => 3, 25 => 0, default => 120
                };
                $lote = LoteInventario::query()->firstOrCreate(['codigo_lote' => 'DEMO-DIV-'.$codigo], [
                    'inventario_id' => $inventario->id, 'proveedor_id' => $proveedor->id,
                    'cantidad_inicial' => $cantidad, 'cantidad_disponible' => $cantidad, 'costo_unitario' => $costo,
                    'precio_venta' => $precio, 'margen_porcentaje' => 66.67, 'fecha_ingreso' => now()->subWeeks(53)->toDateString(),
                    'estado' => 'activo', 'registrado_por' => $admin->id, 'clave_ingreso' => (string) Str::uuid(),
                ]);
                if ($lote->wasRecentlyCreated && $cantidad > 0) {
                    MovimientoInventario::query()->create([
                        'lote_id' => $lote->id, 'usuario_id' => $admin->id, 'tipo_movimiento' => 'entrada',
                        'cantidad' => $cantidad, 'cantidad_anterior' => 0, 'cantidad_nueva' => $cantidad,
                        'motivo' => 'Inventario de demostración del catálogo diverso', 'referencia_tipo' => 'seeder',
                        'created_at' => now()->subWeeks(53), 'updated_at' => now()->subWeeks(53),
                    ]);
                }
            }
        });
        $this->call(ImagenesCatalogoSeeder::class);
        Cache::forget('asistente:vectores-sincronizados');
    }

    /** @return list<array{string, string, string, string, int, string}> */
    private function catalogo(): array
    {
        return [
            ['Bloques ciudad creativa', 'Construcción', 'LEGO', '6+', 280, 'Bloques de construcción para armar casas, vehículos y una ciudad. Juego creativo con piezas pequeñas.'],
            ['Bloques animales grandes', 'Construcción', 'Mega Bloks', '2-5', 145, 'Bloques grandes de animales para primeras construcciones, coordinación y juego creativo.'],
            ['Kit construcción magnética', 'Construcción', 'Genérica', '6+', 220, 'Piezas magnéticas para construir figuras geométricas y estructuras. Requiere supervisión adulta.'],
            ['Auto de carreras miniatura', 'Vehículos', 'Hot Wheels', '3+', 45, 'Auto de juguete de carreras, ruedas libres y carrocería metálica para pistas.'],
            ['Pista de carreras doble', 'Vehículos', 'Hot Wheels', '5+', 260, 'Pista de juguete para competencias de autos, rampas y carreras.'],
            ['Camión de bomberos', 'Vehículos', 'Genérica', '3+', 130, 'Camión de bomberos de juguete para rescates e historias de vehículos.'],
            ['Muñeca exploradora', 'Muñecas', 'Barbie', '3+', 185, 'Muñeca con accesorios para aventuras, exploración y juego de roles.'],
            ['Casa de muñecas compacta', 'Muñecas', 'Genérica', '4+', 420, 'Casa de muñecas con habitaciones y muebles para juego imaginativo.'],
            ['Bebé con accesorios', 'Muñecas', 'Genérica', '3+', 155, 'Muñeco bebé con biberón y accesorios para juego de cuidado.'],
            ['Oso de peluche suave', 'Peluches', 'Genérica', '3+', 95, 'Peluche de oso suave para abrazar y acompañar juegos tranquilos.'],
            ['Peluche dinosaurio verde', 'Peluches', 'Genérica', '3+', 110, 'Dinosaurio de peluche suave, tema prehistórico para regalos.'],
            ['Peluche conejo', 'Peluches', 'Genérica', '3+', 85, 'Conejo de peluche suave para juego imaginativo y regalos.'],
            ['Rompecabezas dinosaurios', 'Rompecabezas', 'Ravensburger', '6+', 120, 'Rompecabezas de dinosaurios para desarrollar observación, paciencia y coordinación.'],
            ['Rompecabezas sistema solar', 'Rompecabezas', 'Genérica', '8+', 140, 'Rompecabezas educativo de planetas y espacio para aprender sobre el sistema solar.'],
            ['Rompecabezas animales', 'Rompecabezas', 'Genérica', '3-6', 65, 'Rompecabezas de animales con piezas grandes para primeras actividades de asociación.'],
            ['Juego UNO clásico', 'Juegos de mesa', 'Mattel', '7+', 70, 'Juego de cartas UNO de colores y números para jugar en familia.'],
            ['Ajedrez escolar', 'Juegos de mesa', 'Genérica', '6+', 95, 'Tablero de ajedrez para aprender estrategia, atención y planificación.'],
            ['Juego memoria de animales', 'Juegos de mesa', 'Genérica', '4+', 60, 'Juego de memoria para encontrar parejas de animales y ejercitar la atención.'],
            ['Kit experimentos iniciales', 'Educativos', 'Genérica', '8+', 190, 'Kit de ciencia con actividades de experimentación; requiere supervisión adulta.'],
            ['Ábaco de colores', 'Educativos', 'Genérica', '3+', 55, 'Ábaco para contar, reconocer colores y practicar operaciones iniciales.'],
            ['Kit excavación dinosaurios', 'Educativos', 'Genérica', '7+', 135, 'Actividad de excavación de fósiles de dinosaurios para exploración y ciencia con supervisión.'],
            ['Set figuras dinosaurios', 'Figuras', 'Genérica', '4+', 90, 'Figuras de dinosaurios para escenas prehistóricas, aprendizaje y juego imaginativo.'],
            ['Figura astronauta', 'Figuras', 'Genérica', '6+', 105, 'Figura de astronauta para aventuras espaciales y exploración del sistema solar.'],
            ['Set animales de granja', 'Figuras', 'Genérica', '3+', 80, 'Figuras de animales de granja para reconocimiento de especies y juego de roles.'],
            ['Plastilina creativa', 'Arte', 'Play-Doh', '3+', 50, 'Plastilina de colores para modelar formas. Producto de demostración con pocas existencias.'],
            ['Set acuarelas infantiles', 'Arte', 'Genérica', '5+', 40, 'Set de acuarelas para pintura y creatividad. Producto de demostración agotado.'],
        ];
    }
}
