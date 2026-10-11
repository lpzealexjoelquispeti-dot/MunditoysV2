<?php

use App\Livewire\Admin\Categorias;
use App\Livewire\Admin\Marcas;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\User;
use App\Services\CategoriaService;
use App\Services\MarcaService;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

dataset('catalogos administrativos', [
    'categorías' => [Categorias::class, Categoria::class, CategoriaService::class, 'categorias'],
    'marcas' => [Marcas::class, Marca::class, MarcaService::class, 'marcas'],
]);

beforeEach(function () {
    Storage::fake('public');
    $this->seed([PermisosSeeder::class, RolesSeeder::class]);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Administrador');
    $this->actingAs($this->admin);
});

test('crea edita reemplaza y conserva imágenes con una clave estable', function ($componente, $modelo, $servicio, $tabla) {
    $form = Livewire::test($componente)->call('crear')
        ->set('nombre', 'Juguetes educativos')
        ->set('descripcion', 'Juguetes para aprender de forma divertida.')
        ->set('imagen', UploadedFile::fake()->image('original.jpg'))
        ->assertSeeHtml('wire:key="imagen-nueva"')
        ->call('guardar')->assertHasNoErrors()->assertSet('mostrarFormulario', false)
        ->assertDispatched('alerta-producto');
    $registro = $modelo::query()->sole();
    $rutaOriginal = $registro->imagen;
    Storage::disk('public')->assertExists($rutaOriginal);
    expect($registro->estado)->toBeTrue();

    $form->call('editar', $registro->id)
        ->assertSeeHtml('wire:key="imagen-'.$registro->id.'"')
        ->set('descripcion', 'Descripción actualizada sin reemplazar la imagen.')
        ->call('guardar')->assertHasNoErrors();
    expect($registro->fresh()->imagen)->toBe($rutaOriginal);

    $form->call('editar', $registro->id)
        ->set('imagen', UploadedFile::fake()->image('nueva.png'))
        ->assertSeeHtml('wire:key="imagen-'.$registro->id.'"')
        ->call('quitarImagenNueva')->assertSet('imagen', null)
        ->set('imagen', UploadedFile::fake()->image('reemplazo.webp'))
        ->call('guardar')->assertHasNoErrors();
    Storage::disk('public')->assertMissing($rutaOriginal);
    Storage::disk('public')->assertExists($registro->fresh()->imagen);
})->with('catalogos administrativos');

test('valida datos e imágenes en español sin persistir entradas inválidas', function ($componente, $modelo, $servicio, $tabla) {
    $form = Livewire::test($componente)->call('crear')->call('guardar')
        ->assertHasErrors(['nombre' => 'required', 'descripcion' => 'required', 'imagen' => 'required'])
        ->assertSee('Selecciona una imagen');
    $form->set('nombre', '<script>')->set('descripcion', 'corta')
        ->set('imagen', UploadedFile::fake()->create('falso.jpg', 1, 'text/plain'))
        ->call('guardar')->assertHasErrors(['nombre' => 'regex', 'descripcion' => 'min', 'imagen' => 'image']);
    $form->set('imagen', UploadedFile::fake()->image('grande.jpg')->size(3073))
        ->assertHasErrors(['imagen' => 'max'])->assertSee('La imagen debe pesar como máximo 3 MB.');
    $this->assertDatabaseCount($tabla, 0);
})->with('catalogos administrativos');

test('busca filtra usa papelera y rechaza nombres duplicados sin distinguir mayúsculas', function ($componente, $modelo, $servicio, $tabla) {
    $registro = $modelo::factory()->create(['nombre' => 'Bloques creativos', 'imagen' => 'catalogo.jpg']);
    $inactivo = $modelo::factory()->create(['nombre' => 'Robots espaciales', 'estado' => false]);
    $form = Livewire::test($componente)->assertSee('Bloques creativos')->assertDontSee('Robots espaciales')
        ->set('filtroEstado', 'inactivo')->assertSee('Robots espaciales')->assertDontSee('Bloques creativos')
        ->set('filtroEstado', 'todos')->set('buscar', 'BLOQUES')->assertSee('Bloques creativos')
        ->assertDontSee('Robots espaciales')->call('limpiarFiltros')
        ->call('eliminar', $registro->id)->assertDispatched('alerta-producto');
    $this->assertSoftDeleted($registro);
    $form->call('crear')->set('nombre', 'bloques creativos')->assertHasErrors('nombre')
        ->assertSee('incluso en la papelera')
        ->call('cerrarFormulario')->set('filtroEstado', 'eliminados')
        ->assertSee('Bloques creativos')->call('verDetalle', $registro->id)->assertSet('mostrarDetalle', true)
        ->call('cerrarDetalle')->call('restaurar', $registro->id);
    $this->assertNotSoftDeleted($registro);
    expect($inactivo->fresh()->estado)->toBeFalse();
})->with('catalogos administrativos');

test('no permite desactivar o eliminar catálogos con productos asociados', function ($componente, $modelo, $servicio, $tabla) {
    $registro = $modelo::factory()->create([
        'nombre' => 'Juguetes educativos', 'descripcion' => 'Descripción de juguetes educativos.', 'imagen' => 'actual.jpg',
    ]);
    Producto::factory()->create([$tabla === 'categorias' ? 'categoria_id' : 'marca_id' => $registro->id]);
    Livewire::test($componente)->call('eliminar', $registro->id)
        ->assertDispatched('alerta-producto', icono: 'warning')
        ->call('editar', $registro->id)->set('estado', false)->call('guardar')
        ->assertHasErrors('estado');
    $this->assertNotSoftDeleted($registro);
    expect($registro->fresh()->estado)->toBeTrue();
})->with('catalogos administrativos');

test('las rutas renderizan el shell y rechazan clientes sin permisos', function ($componente, $modelo, $servicio, $tabla) {
    $this->get(route('admin.'.$tabla))->assertOk()->assertSee('Mi perfil');
    $cliente = User::factory()->create();
    $this->actingAs($cliente)->get(route('admin.'.$tabla))->assertForbidden();
    Livewire::actingAs($cliente)->test($componente)->assertForbidden();
})->with('catalogos administrativos');

test('la base de datos también protege la unicidad de nombres', function ($componente, $modelo, $servicio, $tabla) {
    $modelo::factory()->create(['nombre' => 'Bloques']);
    expect(fn () => $modelo::factory()->create(['nombre' => ' bloques ']))
        ->toThrow(UniqueConstraintViolationException::class);
})->with('catalogos administrativos');

test('escapa descripciones y rechaza filtros de orden manipulados', function ($componente, $modelo, $servicio, $tabla) {
    $registro = $modelo::factory()->create(['nombre' => 'Bloques seguros', 'descripcion' => '<script>alert(1)</script>']);
    Livewire::test($componente)->call('verDetalle', $registro->id)
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertSeeHtml('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->set('orden', 'nombre; DROP TABLE users')->assertHasErrors('orden');
    $this->assertDatabaseCount($tabla, 1);
})->with('catalogos administrativos');
