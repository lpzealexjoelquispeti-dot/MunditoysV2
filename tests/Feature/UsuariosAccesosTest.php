<?php

use App\Livewire\Admin\Usuarios;
use App\Mail\InvitacionEmpleado;
use App\Models\Auditoria;
use App\Models\User;
use App\Services\UsuarioAccesoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

test('invita desde livewire sin revelar ni guardar la contraseña del administrador', function (): void {
    Mail::fake();
    $admin = administradorAccesos();
    $componente = Livewire::test(Usuarios::class)->call('crear');
    foreach (datosEmpleadoAccesos() as $campo => $valor) {
        $componente->set($campo, $valor);
    }
    $componente->call('guardar', 'AdminSegura123!')->assertHasNoErrors()->assertSet('mostrarFormulario', false)
        ->assertDispatched('alerta-producto');
    $usuario = User::where('email', 'ana@example.com')->firstOrFail();
    expect($usuario->estado)->toBeFalse()
        ->and($usuario->invitacion_pendiente)->toBeTrue()
        ->and($usuario->apellido_materno)->toBeNull()
        ->and($usuario->telefono)->toBe('71234567')
        ->and($usuario->hasRole('Inventario'))->toBeTrue()
        ->and(Hash::check('AdminSegura123!', $usuario->password))->toBeFalse();
    Mail::assertSent(InvitacionEmpleado::class, fn ($mail) => $mail->hasTo('ana@example.com') && $mail->rol === 'Inventario');
    $auditoria = Auditoria::where('registro_id', $usuario->id)->get()->toJson();
    expect($auditoria)->toContain('usuario_invitado')->toContain('invitacion_enviada')
        ->not->toContain('AdminSegura123!')->not->toContain('invitacion_token_hash')->not->toContain($usuario->password);
    expect($componente->snapshot['data'])->not->toHaveKey('passwordAdministrador');
    expect(Auditoria::where('registro_id', $usuario->id)->first()->usuario_id)->toBe($admin->id);
});

test('valida datos del empleado en español', function (string $campo, mixed $valor, string $mensaje): void {
    Mail::fake();
    administradorAccesos();
    $componente = Livewire::test(Usuarios::class);
    foreach (datosEmpleadoAccesos([$campo => $valor]) as $nombre => $dato) {
        $componente->set($nombre, $dato);
    }
    $componente->call('guardar', 'AdminSegura123!')->assertHasErrors([$campo])->assertSee($mensaje);
    Mail::assertNothingSent();
    expect(User::where('email', 'ana@example.com')->exists())->toBeFalse();
})->with([
    ['name', '', 'El campo nombre es obligatorio.'],
    ['name', 'A', 'al menos 2 caracteres'],
    ['name', str_repeat('a', 101), '100'],
    ['name', 'Ana123', 'El nombre admite letras'],
    ['name', '---', 'El nombre admite letras'],
    ['apellido_paterno', '', 'Escribe al menos uno'],
    ['apellido_paterno', 'Flores@', 'Escribe un apellido válido'],
    ['apellido_materno', '123', 'Escribe un apellido válido'],
    ['telefono', '21234567', 'El celular debe tener 8 dígitos'],
    ['telefono', '7123456', 'El celular debe tener 8 dígitos'],
    ['telefono', '712345678', 'El celular debe tener 8 dígitos'],
    ['email', 'no-es-correo', 'correo electrónico'],
    ['rolUsuario', 'Superadmin', 'Selecciona uno de los roles permitidos.'],
    ['rolUsuario', 'Cliente', 'Selecciona uno de los roles permitidos.'],
]);

test('crear vendedor requiere asignar una sucursal con validacion en espanol', function (): void {
    Mail::fake();
    administradorAccesos();
    $componente = Livewire::test(Usuarios::class);
    foreach (datosEmpleadoAccesos(['rolUsuario' => 'Vendedor', 'sucursal_id' => null]) as $nombre => $dato) {
        $componente->set($nombre, $dato);
    }
    $componente->call('guardar', 'AdminSegura123!')
        ->assertHasErrors(['sucursal_id'])
        ->assertSee('Debes asignar una sucursal de trabajo para el personal de ventas.');
});

test('rechaza contraseña administrativa incorrecta y limita los intentos', function (): void {
    Mail::fake();
    $admin = administradorAccesos();
    $service = app(UsuarioAccesoService::class);
    for ($intento = 0; $intento < 5; $intento++) {
        expect(fn () => $service->crear(datosEmpleadoAccesos(), 'incorrecta'))->toThrow(ValidationException::class, 'La contraseña del administrador no es correcta.');
    }
    expect(fn () => $service->crear(datosEmpleadoAccesos(), 'AdminSegura123!'))->toThrow(ValidationException::class, 'Demasiados intentos.');
    Mail::assertNothingSent();
    expect(User::count())->toBe(1);
});

test('solo un administrador autorizado puede gestionar cuentas', function (string $rol): void {
    Mail::fake();
    administradorAccesos();
    $usuario = User::factory()->create();
    $usuario->assignRole($rol);
    $this->actingAs($usuario)->get(route('admin.usuarios'))->assertForbidden();
    expect(Gate::forUser($usuario)->allows('administrar', User::class))->toBeFalse();
    expect(fn () => app(UsuarioAccesoService::class)->crear(datosEmpleadoAccesos(), 'password'))->toThrow(AuthorizationException::class);
    Mail::assertNothingSent();
})->with(['Cliente', 'Vendedor', 'Inventario', 'Logistica']);

test('el visitante no accede a usuarios', function (): void {
    $this->get(route('admin.usuarios'))->assertRedirect(route('login'));
});

test('los empleados entran a su panel sin acceder a usuarios', function (string $rol, string $ruta, bool $inventario): void {
    administradorAccesos();
    $empleado = User::factory()->create();
    $empleado->assignRole($rol);
    $this->actingAs($empleado)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Usuarios y accesos');
    $this->get(route($ruta))->assertOk();
    expect(Gate::forUser($empleado)->allows('gestionar inventario'))->toBe($inventario);
})->with([
    ['Vendedor', 'admin.productos', false],
    ['Inventario', 'admin.inventario', true],
    ['Logistica', 'admin.inventario', true],
]);

test('el vendedor no ve botones de escritura del catálogo ni accede a inventario', function (): void {
    administradorAccesos();
    $empleado = User::factory()->create();
    $empleado->assignRole('Vendedor');
    $this->actingAs($empleado)->get(route('admin.productos'))->assertOk()->assertDontSee('Nuevo producto')->assertDontSee('Registrar producto');
    $this->get(route('admin.inventario'))->assertForbidden();
});

test('rechaza correos duplicados aun con mayúsculas o baja lógica', function (): void {
    Mail::fake();
    administradorAccesos();
    $existente = User::factory()->create(['email' => 'ANA@example.com']);
    $existente->delete();
    expect(fn () => app(UsuarioAccesoService::class)->crear(datosEmpleadoAccesos(), 'AdminSegura123!'))
        ->toThrow(ValidationException::class, 'Este correo ya está registrado.');
    Mail::assertNothingSent();
});

test('ignora claves adicionales y normaliza el correo', function (): void {
    Mail::fake();
    administradorAccesos();
    app(UsuarioAccesoService::class)->crear(datosEmpleadoAccesos([
        'email' => ' ANA@EXAMPLE.COM ', 'estado' => true, 'password' => 'Atacante123!', 'tipo_usuario' => 'admin',
    ]), 'AdminSegura123!');
    $usuario = User::where('email', 'ana@example.com')->firstOrFail();
    expect($usuario->estado)->toBeFalse()->and($usuario->tipo_usuario)->toBe('empleado')
        ->and(Hash::check('Atacante123!', $usuario->password))->toBeFalse();
    Mail::assertSent(InvitacionEmpleado::class);
});

test('cambia el rol y revoca sesiones sin perder la auditoría', function (): void {
    administradorAccesos();
    $empleado = User::factory()->create(['email' => 'ana@example.com']);
    $empleado->assignRole('Vendedor');
    config(['session.driver' => 'database']);
    DB::table('sessions')->insert(['id' => 'sesion-empleado', 'user_id' => $empleado->id, 'payload' => '', 'last_activity' => time()]);
    app(UsuarioAccesoService::class)->actualizar($empleado->id, datosEmpleadoAccesos(), 'AdminSegura123!');
    expect($empleado->fresh()->hasRole('Inventario'))->toBeTrue();
    expect($empleado->fresh()->sesion_version)->toBe(1);
    $this->assertDatabaseMissing('sessions', ['id' => 'sesion-empleado']);
    $this->assertDatabaseHas('auditorias', ['accion' => 'rol_actualizado', 'registro_id' => $empleado->id]);
});

test('protege al último administrador contra la baja y la pérdida del rol', function (): void {
    $admin = administradorAccesos();
    $service = app(UsuarioAccesoService::class);
    expect(fn () => $service->ejecutarAccion($admin->id, 'desactivar', 'AdminSegura123!'))->toThrow(ValidationException::class, 'Debe permanecer al menos un administrador activo.');
    expect(fn () => $service->actualizar($admin->id, datosEmpleadoAccesos(['email' => $admin->email]), 'AdminSegura123!'))->toThrow(ValidationException::class, 'Debe permanecer al menos un administrador activo.');
    expect($admin->fresh()->estado)->toBeTrue();
});

test('un administrador no puede desactivar ni editar a otro administrador ni a sí mismo', function (): void {
    $admin = administradorAccesos();
    $otro = User::factory()->create();
    $otro->assignRole('Administrador');
    $service = app(UsuarioAccesoService::class);

    // No puede desactivarse a sí mismo
    expect(fn () => $service->ejecutarAccion($admin->id, 'desactivar', 'AdminSegura123!'))->toThrow(ValidationException::class, 'No puedes desactivar tu propia cuenta');

    // No puede desactivar a otro administrador
    expect(fn () => $service->ejecutarAccion($otro->id, 'desactivar', 'AdminSegura123!'))->toThrow(ValidationException::class, 'Un administrador no puede dar de baja ni desactivar a otro administrador.');
    expect($otro->fresh()->estado)->toBeTrue();

    // No puede editar los datos de otro administrador
    expect(fn () => $service->actualizar($otro->id, datosEmpleadoAccesos(['email' => $otro->email, 'rolUsuario' => 'Administrador']), 'AdminSegura123!'))
        ->toThrow(ValidationException::class, 'Un administrador no puede editar los datos de otro administrador');

    // Sí puede desactivar a un empleado de otro rol (ej. Vendedor o Inventario)
    $empleado = User::factory()->create();
    $empleado->assignRole('Vendedor');
    $service->ejecutarAccion($empleado->id, 'desactivar', 'AdminSegura123!');
    expect($empleado->fresh()->estado)->toBeFalse();
    $service->ejecutarAccion($empleado->id, 'reactivar', 'AdminSegura123!');
    expect($empleado->fresh()->estado)->toBeTrue()->and($empleado->fresh()->sesion_version)->toBe(2);
    $this->assertDatabaseHas('auditorias', ['accion' => 'cuenta_reactivada', 'registro_id' => $empleado->id]);
});

test('bloquea una sesión abierta de una cuenta desactivada', function (): void {
    administradorAccesos();
    $empleado = User::factory()->create();
    $empleado->assignRole('Inventario');
    app(UsuarioAccesoService::class)->ejecutarAccion($empleado->id, 'desactivar', 'AdminSegura123!');
    $this->actingAs($empleado)->get(route('admin.inventario'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('una sesión antigua no revive al reactivar una cuenta', function (): void {
    administradorAccesos();
    $empleado = User::factory()->create();
    $empleado->assignRole('Inventario');
    $service = app(UsuarioAccesoService::class);
    $service->ejecutarAccion($empleado->id, 'desactivar', 'AdminSegura123!');
    $service->ejecutarAccion($empleado->id, 'reactivar', 'AdminSegura123!');
    $this->actingAs($empleado->fresh())->withSession(['cuenta_version' => 0])->get(route('admin.inventario'))->assertRedirect(route('login'));
});

test('no convierte clientes en empleados ni altera el correo de acceso', function (): void {
    administradorAccesos();
    $cliente = User::factory()->create(['email' => 'ana@example.com']);
    $cliente->assignRole('Cliente');
    $service = app(UsuarioAccesoService::class);
    expect(fn () => $service->actualizar($cliente->id, datosEmpleadoAccesos(), 'AdminSegura123!'))->toThrow(ValidationException::class, 'No se puede convertir');
    expect(fn () => $service->actualizar($cliente->id, datosEmpleadoAccesos(['email' => 'otro@example.com', 'rolUsuario' => 'Cliente']), 'AdminSegura123!'))->toThrow(ValidationException::class, 'El correo de acceso no se cambia');
});

test('busca filtra y muestra datos escapados sin exponer secretos', function (): void {
    administradorAccesos();
    $empleado = User::factory()->create(['name' => '<script>alert(1)</script>', 'email' => 'unico@example.com']);
    $empleado->assignRole('Vendedor');
    $componente = Livewire::test(Usuarios::class)->set('buscar', 'unico@example.com')
        ->assertSee('unico@example.com')->assertDontSee('<script>alert(1)</script>', false)
        ->call('verDetalle', $empleado->id);
    expect($componente->get('detalle'))->not->toHaveKey('password')->not->toHaveKey('invitacion_token_hash');
    $componente->set('rol', 'Inventario')->assertSee('0 cuentas encontradas');
});

test('componente Livewire no permite editar ni desactivar a otro administrador desde la interfaz', function (): void {
    $admin = administradorAccesos();
    $otroAdmin = User::factory()->create(['name' => 'Carlos Admin', 'email' => 'carlos@example.com']);
    $otroAdmin->assignRole('Administrador');

    $this->actingAs($admin);

    Livewire::test(Usuarios::class)
        ->call('editar', $otroAdmin->id)
        ->assertSet('mostrarFormulario', false)
        ->assertDispatched('alerta-producto', function ($event, $params) {
            return $params['titulo'] === 'Acción no permitida';
        });
});

test('rechaza filtros de orden inyectados', function (): void {
    administradorAccesos();
    expect(fn () => app(UsuarioAccesoService::class)->listar(['grupo' => 'empleados', 'buscar' => '', 'rol' => '', 'estado' => 'todos', 'orden' => 'name; DROP TABLE users']))
        ->toThrow(ValidationException::class);
    expect(User::count())->toBe(1);
});

test('un administrador desactivado no puede invocar servicios con una instancia antigua', function (): void {
    $admin = administradorAccesos();
    User::whereKey($admin->id)->update(['estado' => false]);
    expect(fn () => app(UsuarioAccesoService::class)->listar(['grupo' => 'empleados', 'buscar' => '', 'rol' => '', 'estado' => 'todos', 'orden' => 'recientes']))
        ->toThrow(HttpException::class);
});
