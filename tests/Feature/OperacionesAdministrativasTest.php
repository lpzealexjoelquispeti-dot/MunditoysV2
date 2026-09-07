<?php

use App\Livewire\Admin\Almacenes;
use App\Livewire\Admin\Proveedores;
use App\Livewire\Admin\Sucursales;
use App\Models\Almacen;
use App\Models\Inventario;
use App\Models\LoteInventario;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\AlmacenService;
use App\Services\InventarioService;
use App\Services\ProveedorService;
use App\Services\SucursalService;
use Database\Seeders\PermisosSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function administradorOperaciones(): User
{
    test()->seed([PermisosSeeder::class, RolesSeeder::class]);
    $usuario = User::factory()->create();
    $usuario->assignRole('Administrador');
    test()->actingAs($usuario);

    return $usuario;
}

function datosSucursalOperaciones(): array
{
    return [
        'nombre' => 'Sucursal Centro', 'direccion' => 'Calle Comercio, N.º 123',
        'zona' => 'Centro', 'telefono' => '+591 71234567', 'latitud' => '-16.4950000', 'longitud' => '-68.1330000', 'estado' => true,
    ];
}

function datosProveedorOperaciones(): array
{
    return [
        'empresa' => 'Juguetes del Sur S.R.L.', 'nombre_contacto' => 'Ana Pérez',
        'telefono' => '+59171234567', 'email' => 'CONTACTO@example.com',
        'direccion' => 'Avenida del Sur, N.º 123', 'nit' => '123456789', 'estado' => true,
    ];
}

function datosModuloOperaciones(string $modulo): array
{
    return match ($modulo) {
        'sucursales' => datosSucursalOperaciones(),
        'proveedores' => datosProveedorOperaciones(),
        'almacenes' => ['nombre' => 'Almacén 1', 'sucursal_id' => (string) Sucursal::factory()->create()->id, 'tipo' => 'principal', 'estado' => true],
    };
}

dataset('modulos operativos', [
    'sucursales' => [Sucursales::class, Sucursal::class, SucursalService::class, 'sucursales', 'nombre'],
    'almacenes' => [Almacenes::class, Almacen::class, AlmacenService::class, 'almacenes', 'nombre'],
    'proveedores' => [Proveedores::class, Proveedor::class, ProveedorService::class, 'proveedores', 'empresa'],
]);

test('crea edita consulta y restaura registros operativos desde sus modales', function ($componente, $modelo, $servicio, $modulo, $campoNombre) {
    administradorOperaciones();
    $datos = datosModuloOperaciones($modulo);
    $form = Livewire::test($componente)->call('crear');
    foreach ($datos as $campo => $valor) {
        $form->set($campo, $valor);
    }
    $form->call('guardar')->assertHasNoErrors()->assertSet('mostrarFormulario', false)->assertDispatched('alerta-producto');
    $registro = $modelo::query()->where($campoNombre, $datos[$campoNombre])->sole();
    expect($registro->estado)->toBeTrue();
    $form->call('editar', $registro->id)->set($campoNombre, 'Nuevo nombre')->call('guardar')->assertHasNoErrors();
    expect($registro->fresh()->{$campoNombre})->toBe('Nuevo nombre');
    $form->call('verDetalle', $registro->id)->assertSet('mostrarDetalle', true)->assertSee('Nuevo nombre')
        ->call('cerrarDetalle')->call('eliminar', $registro->id);
    $this->assertSoftDeleted($registro);
    $form->set('filtroEstado', 'eliminados')->assertSee('Nuevo nombre')->call('restaurar', $registro->id);
    $this->assertNotSoftDeleted($registro);
    expect(Gate::allows('forceDelete', $registro))->toBeFalse();
})->with('modulos operativos');

test('impide guardar formularios vacíos y muestra validaciones en español', function ($componente, $modelo, $servicio, $modulo, $campoNombre) {
    administradorOperaciones();
    Livewire::test($componente)->call('crear')->call('guardar')
        ->assertHasErrors([$campoNombre => 'required'])->assertSee('es obligatorio');
})->with('modulos operativos');

test('protege rutas componentes y servicios de usuarios sin permisos', function ($componente, $modelo, $servicio, $modulo, $campoNombre) {
    $cliente = User::factory()->create();
    $datos = datosModuloOperaciones($modulo);
    $this->actingAs($cliente)->get(route('admin.'.$modulo))->assertForbidden();
    Livewire::actingAs($cliente)->test($componente)->assertForbidden();
    expect(fn () => app($servicio)->crear($datos))->toThrow(AuthorizationException::class);
})->with('modulos operativos');

test('las rutas muestran los tres nuevos enlaces y piden autenticación', function ($componente, $modelo, $servicio, $modulo, $campoNombre) {
    $this->get(route('admin.'.$modulo))->assertRedirect(route('login'));
    administradorOperaciones();
    $this->get(route('admin.'.$modulo))->assertOk()->assertSee('Sucursales')->assertSee('Almacenes')->assertSee('Proveedores');
})->with('modulos operativos');

test('filtra por nombre y estado y rechaza nombres duplicados incluidos los eliminados', function ($componente, $modelo, $servicio, $modulo, $campoNombre) {
    administradorOperaciones();
    $datos = datosModuloOperaciones($modulo);
    $datos[$campoNombre] = 'Registro filtrable';
    $registro = app($servicio)->crear($datos);
    Livewire::test($componente)->set('buscar', 'NO COINCIDE')->assertDontSee($registro->{$campoNombre})
        ->call('limpiarFiltros')->assertSee($registro->{$campoNombre})
        ->set('filtroEstado', 'inactivo')->assertDontSee($registro->{$campoNombre});
    app($servicio)->eliminar($registro->id);
    $datos[$campoNombre] = mb_strtolower($datos[$campoNombre]);
    expect(fn () => app($servicio)->crear($datos))->toThrow(ValidationException::class);
    $this->assertSoftDeleted($registro);
})->with('modulos operativos');

test('rechaza nombres de almacén con signos o solo números', function (string $nombre) {
    administradorOperaciones();
    $datos = datosModuloOperaciones('almacenes');
    $datos['nombre'] = $nombre;
    try {
        app(AlmacenService::class)->crear($datos);
        $this->fail('Se aceptó un nombre inválido.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('nombre');
        expect($exception->errors()['nombre'][0])->toContain('No se admiten signos');
    }
    $this->assertDatabaseCount('almacenes', 0);
})->with(['Almacén #1', 'Almacén@1', 'Almacén-1', '12345', 'Almacén 🧸']);

test('permite números en almacenes y limita nombres duplicados por sucursal', function () {
    administradorOperaciones();
    $datos = datosModuloOperaciones('almacenes');
    $uno = app(AlmacenService::class)->crear($datos);
    $datos['sucursal_id'] = (string) Sucursal::factory()->create()->id;
    $dos = app(AlmacenService::class)->crear($datos);
    expect($uno->nombre)->toBe('Almacén 1');
    expect($dos->nombre)->toBe('Almacén 1');
    expect($uno->sucursal_id)->not->toBe($dos->sucursal_id);
});

test('el almacén valida tipo sucursal inexistente e inactiva', function () {
    administradorOperaciones();
    $datos = datosModuloOperaciones('almacenes');
    expect(fn () => app(AlmacenService::class)->crear(array_replace($datos, ['tipo' => 'cualquier cosa'])))
        ->toThrow(ValidationException::class);
    expect(fn () => app(AlmacenService::class)->crear(array_replace($datos, ['sucursal_id' => '999999'])))
        ->toThrow(ValidationException::class);
    Sucursal::findOrFail($datos['sucursal_id'])->update(['estado' => false]);
    expect(fn () => app(AlmacenService::class)->crear($datos))->toThrow(ValidationException::class);
    $this->assertDatabaseCount('almacenes', 0);
});

test('no permite cambiar la sucursal de un almacén con historial ni darlo de baja con stock', function () {
    administradorOperaciones();
    $datos = datosModuloOperaciones('almacenes');
    $almacen = app(AlmacenService::class)->crear($datos);
    $inventario = Inventario::factory()->create(['almacen_id' => $almacen->id]);
    $lote = LoteInventario::factory()->create(['inventario_id' => $inventario->id]);
    $otra = Sucursal::factory()->create();
    expect(fn () => app(AlmacenService::class)->actualizar($almacen->id, array_replace($datos, ['sucursal_id' => (string) $otra->id])))
        ->toThrow(ValidationException::class);
    expect(fn () => app(AlmacenService::class)->actualizar($almacen->id, array_replace($datos, ['estado' => false])))
        ->toThrow(ValidationException::class);
    Livewire::test(Almacenes::class)->call('eliminar', $almacen->id)->assertDispatched('alerta-producto', icono: 'warning');
    $this->assertNotSoftDeleted($almacen);
    $lote->update(['cantidad_disponible' => 0]);
    app(AlmacenService::class)->eliminar($almacen->id);
    $this->assertSoftDeleted($almacen);
    expect($inventario->fresh()->almacen->nombre)->toBe('Almacén 1');
    $this->assertDatabaseCount('lotes_inventario', 1);
});

test('bloquea sucursales con almacenes activos y restauraciones de almacenes sin sucursal disponible', function () {
    administradorOperaciones();
    $datos = datosSucursalOperaciones();
    $sucursal = app(SucursalService::class)->crear($datos);
    $almacen = Almacen::factory()->create(['sucursal_id' => $sucursal->id]);
    expect(fn () => app(SucursalService::class)->eliminar($sucursal->id))->toThrow(ValidationException::class);
    expect(fn () => app(SucursalService::class)->actualizar($sucursal->id, array_replace($datos, ['estado' => false])))
        ->toThrow(ValidationException::class);
    app(AlmacenService::class)->eliminar($almacen->id);
    app(SucursalService::class)->eliminar($sucursal->id);
    Livewire::test(Almacenes::class)->call('restaurar', $almacen->id)->assertDispatched('alerta-producto', icono: 'warning');
    $this->assertSoftDeleted($almacen);
    app(SucursalService::class)->restaurar($sucursal->id);
    app(AlmacenService::class)->restaurar($almacen->id);
    $this->assertNotSoftDeleted($almacen);
});

test('la sucursal valida el punto en el servidor e ignora ciudad y metadatos manipulados', function () {
    administradorOperaciones();
    $datos = datosSucursalOperaciones();
    $sucursal = app(SucursalService::class)->crear($datos + ['ciudad' => 'Otra ciudad', 'ubicacion_jsonb' => ['falso' => true]]);
    expect($sucursal->ciudad)->toBe('La Paz');
    expect($sucursal->telefono)->toBe('71234567');
    expect($sucursal->ubicacion_jsonb['coordinates'])->toBe([-68.133, -16.495]);
    expect($sucursal->ubicacion_jsonb)->not->toHaveKey('falso');

    $form = Livewire::test(Sucursales::class)->call('editar', $sucursal->id)
        ->call('seleccionarUbicacion', '-16.5000000', '-68.2000000')->assertHasErrors('latitud')
        ->assertSee('El Alto no está incluido.')
        ->set('latitud', '-16.5000000')->set('longitud', '-68.2000000')->call('guardar')->assertHasErrors('latitud');
    expect($sucursal->fresh()->longitud)->toBe('-68.1330000');
    $form->call('seleccionarUbicacion', '-16.5390000', '-68.0860000')
        ->assertHasNoErrors('latitud')->call('guardar')->assertHasNoErrors();
    expect($sucursal->fresh()->longitud)->toBe('-68.0860000');
});

test('valida campos de proveedor según el atributo', function ($campo, $valor, $fragmento) {
    administradorOperaciones();
    $datos = array_replace(datosProveedorOperaciones(), [$campo => $valor]);
    try {
        app(ProveedorService::class)->crear($datos);
        $this->fail('Se aceptó un proveedor inválido.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($campo);
        expect($exception->errors()[$campo][0])->toContain($fragmento);
    }
    $this->assertDatabaseCount('proveedores', 0);
})->with([
    'contacto con números' => ['nombre_contacto', 'Ana 123', 'solo admite letras'],
    'teléfono corto' => ['telefono', '7123456', '8 dígitos'],
    'teléfono con letras' => ['telefono', '7123abcd', '8 dígitos'],
    'teléfono extranjero' => ['telefono', '+5112345678', '8 dígitos'],
    'teléfono con prefijo inválido' => ['telefono', '91234567', '8 dígitos'],
    'correo inválido' => ['email', 'sin-arroba', 'correo electrónico'],
    'NIT con signos' => ['nit', '1234-56', 'entre 5 y 20'],
    'NIT corto' => ['nit', '1234', 'entre 5 y 20'],
    'NIT con cero inicial' => ['nit', '012345678', 'no comenzar con cero'],
    'dirección con HTML' => ['direccion', '<script>alert(1)</script>', 'dirección'],
    'empresa con etiquetas' => ['empresa', '<b>Empresa</b>', 'Usa letras'],
]);

test('normaliza contacto permite opcionales vacíos y conserva el proveedor histórico', function () {
    administradorOperaciones();
    $datos = datosProveedorOperaciones();
    $proveedor = app(ProveedorService::class)->crear($datos);
    expect($proveedor->telefono)->toBe('71234567');
    expect($proveedor->email)->toBe('contacto@example.com');
    $lote = LoteInventario::factory()->create(['proveedor_id' => $proveedor->id]);
    app(ProveedorService::class)->eliminar($proveedor->id);
    expect($lote->fresh()->proveedor->empresa)->toBe($datos['empresa']);
    expect(app(InventarioService::class)->opciones()['proveedores'])->toBeEmpty();
    $nuevo = app(ProveedorService::class)->crear(array_replace($datos, ['empresa' => 'Otro proveedor', 'email' => '', 'nit' => '']));
    expect($nuevo->email)->toBeNull();
    expect($nuevo->nit)->toBeNull();
    expect(fn () => app(ProveedorService::class)->crear(array_replace($datos, ['empresa' => 'Tercer proveedor'])))
        ->toThrow(ValidationException::class);
});

test('los registros nuevos quedan disponibles en inventario sin crear datos ficticios', function () {
    administradorOperaciones();
    $sucursal = app(SucursalService::class)->crear(datosSucursalOperaciones());
    $almacen = app(AlmacenService::class)->crear([
        'nombre' => 'Almacén Central 1', 'sucursal_id' => (string) $sucursal->id, 'tipo' => 'principal',
    ]);
    $proveedor = app(ProveedorService::class)->crear(datosProveedorOperaciones());
    $opciones = app(InventarioService::class)->opciones();
    expect(array_column($opciones['sucursales'], 'id'))->toContain($sucursal->id);
    expect(array_column($opciones['almacenes'], 'id'))->toContain($almacen->id);
    expect(array_column($opciones['proveedores'], 'id'))->toContain($proveedor->id);
    $this->assertDatabaseCount('inventarios', 0);
});

test('los filtros de almacenes y el contenido histórico no permiten inyección', function () {
    administradorOperaciones();
    $almacen = Almacen::factory()->create(['nombre' => '<script>alert(1)</script>']);
    Livewire::test(Almacenes::class)->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertSeeHtml('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->set('filtroSucursal', '1 OR 1=1')->assertHasErrors('filtroSucursal')
        ->call('limpiarFiltros')->set('orden', 'nombre; DROP TABLE almacenes')->assertHasErrors('orden');
    $this->assertDatabaseHas('almacenes', ['id' => $almacen->id]);
});
