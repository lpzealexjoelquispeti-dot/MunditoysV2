<?php

use App\Models\LoteInventario;
use App\Models\Modelado3D;
use App\Models\Producto;
use App\Models\ProductoImagen;
use Database\Seeders\CatalogoDiversoSeeder;
use Database\Seeders\ImagenesCatalogoSeeder;
use Database\Seeders\MarvelCatalogSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('el catálogo diverso tiene fotos locales sin descargar archivos al ejecutar los seeders', function (): void {
    Storage::fake('public');
    Http::preventStrayRequests();

    $this->seed(CatalogoDiversoSeeder::class);

    $this->assertDatabaseCount('producto_imagenes', 26);
    foreach (Producto::query()->with('imagenes')->get() as $producto) {
        expect($producto->imagenes)->toHaveCount(1);
        $imagen = $producto->imagenes->first();
        expect($imagen->principal)->toBeTrue();
        expect($imagen->orden)->toBe(1);
        Storage::disk('public')->assertExists($imagen->ruta_archivo);
        expect(getimagesize(Storage::disk('public')->path($imagen->ruta_archivo)))->not->toBeFalse();
    }
    Http::assertNothingSent();
});

test('repetir los seeders conserva las fotos propias y los modelos glb de Marvel Legends', function (): void {
    Storage::fake('public');
    $this->seed(CatalogoDiversoSeeder::class);
    $producto = Producto::factory()->create([
        'codigo_producto' => 'MT-THANOS-004', 'nombre' => 'Thanos Marvel', 'precio_venta' => 479,
    ]);
    $foto = ProductoImagen::factory()->create(['producto_id' => $producto->id, 'ruta_archivo' => 'productos/thanos-propio.jpg']);
    Storage::disk('public')->put($foto->ruta_archivo, 'foto propia conservada');
    $modelo = Modelado3D::factory()->manual()->create(['producto_id' => $producto->id, 'modelo_glb_path' => 'modelos-3d/thanos-propio.glb']);

    $this->seed(MarvelCatalogSeeder::class);
    $lote = LoteInventario::query()->whereHas('inventario', fn (Builder $query): Builder => $query->where('producto_id', $producto->id))->firstOrFail();
    $lote->update(['cantidad_disponible' => 51]);
    $this->seed([MarvelCatalogSeeder::class, CatalogoDiversoSeeder::class]);

    expect($producto->fresh()->nombre)->toBe('Thanos Marvel Legends');
    expect($producto->fresh()->precio_venta)->toBe('479.00');
    expect($lote->fresh()->cantidad_disponible)->toBe(51);
    expect($producto->fresh()->imagenes->pluck('id')->all())->toBe([$foto->id]);
    expect(Storage::disk('public')->get($foto->ruta_archivo))->toBe('foto propia conservada');
    expect($modelo->fresh()->producto_id)->toBe($producto->id);
    expect($modelo->fresh()->modelo_glb_path)->toBe('modelos-3d/thanos-propio.glb');
    $this->assertDatabaseCount('productos', 31);
    $this->assertDatabaseCount('producto_imagenes', 31);
    expect(Producto::query()->where('codigo_producto', 'like', 'MT-%')->where('nombre', 'like', '%Marvel Legends%')->count())->toBe(5);
    foreach (Producto::query()->where('nombre', 'like', '%Marvel Legends%')->with('imagenes')->get() as $marvel) {
        Storage::disk('public')->assertExists($marvel->imagenes->first()->ruta_archivo);
    }
});

test('el seeder de imágenes omite los productos inactivos o eliminados', function (): void {
    Storage::fake('public');
    Producto::factory()->create(['codigo_producto' => 'MT-DIV-001', 'estado' => 'inactivo']);
    $eliminado = Producto::factory()->create(['codigo_producto' => 'MT-DIV-002']);
    $eliminado->delete();
    $activo = Producto::factory()->create(['codigo_producto' => 'MT-DIV-003']);

    $this->seed(ImagenesCatalogoSeeder::class);

    $this->assertDatabaseCount('producto_imagenes', 1);
    $this->assertDatabaseHas('producto_imagenes', ['producto_id' => $activo->id]);
});

test('el seeder no registra una imagen corrupta', function (): void {
    Storage::fake('public');
    Storage::fake('local');
    Producto::factory()->create(['codigo_producto' => 'MT-DIV-001']);
    $disco = Storage::disk('local');
    $disco->put('images/catalogo-demo/imagen.jpg', 'archivo corrupto');
    $disco->put('images/catalogo-demo/fuentes.json', json_encode([
        ['codigo' => 'MT-DIV-001', 'archivo' => 'imagen.jpg', 'sha256' => 'hash incorrecto'],
    ]));
    $rutaPublica = public_path();
    app()->usePublicPath($disco->path(''));

    try {
        expect(fn () => app(ImagenesCatalogoSeeder::class)->run())->toThrow(RuntimeException::class, 'no es válida');
        $this->assertDatabaseCount('producto_imagenes', 0);
        expect(Storage::disk('public')->allFiles())->toBe([]);
    } finally {
        app()->usePublicPath($rutaPublica);
    }
});

test('las imágenes de demostración no se cargan en producción', function (): void {
    Storage::fake('public');
    Producto::factory()->create(['codigo_producto' => 'MT-DIV-001']);
    app()->detectEnvironment(fn (): string => 'production');

    try {
        app(ImagenesCatalogoSeeder::class)->run();
        $this->assertDatabaseCount('producto_imagenes', 0);
        expect(Storage::disk('public')->allFiles())->toBe([]);
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});
