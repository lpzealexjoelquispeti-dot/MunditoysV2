<?php

use App\Livewire\Auth\ActivarCuenta;
use App\Mail\InvitacionEmpleado;
use App\Models\User;
use App\Services\InvitacionService;
use App\Services\UsuarioAccesoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;

uses(RefreshDatabase::class);

function ultimaInvitacionAccesos(): array
{
    $mail = Mail::sent(InvitacionEmpleado::class)->last();
    $usuario = User::where('email', $mail->correo)->firstOrFail();

    return [$usuario, basename($mail->urlActivacion), $mail];
}

test('activa una invitación una sola vez y permite elegir la contraseña', function (): void {
    Mail::fake();
    administradorAccesos();
    app(UsuarioAccesoService::class)->crear(datosEmpleadoAccesos(), 'AdminSegura123!');
    [$usuario, $token, $mail] = ultimaInvitacionAccesos();
    Mail::assertSent(InvitacionEmpleado::class);
    expect($usuario->invitacion_token_hash)->toBe(hash('sha256', $token))->not->toBe($token);
    Auth::logout();
    $this->get($mail->urlActivacion)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertSee('Activa tu cuenta');
    Livewire::test(ActivarCuenta::class, ['usuario' => $usuario->id, 'token' => $token])
        ->set('password', 'MiNuevaClave123!')->set('passwordConfirmation', 'MiNuevaClave123!')
        ->call('activar')->assertHasNoErrors()->assertRedirect(route('login'));
    $actual = $usuario->fresh();
    expect($actual->estado)->toBeTrue()->and($actual->invitacion_pendiente)->toBeFalse()
        ->and($actual->invitacion_token_hash)->toBeNull()->and($actual->email_verified_at)->not->toBeNull()
        ->and(Hash::check('MiNuevaClave123!', $actual->password))->toBeTrue();
    $this->assertDatabaseHas('auditorias', ['accion' => 'cuenta_activada', 'registro_id' => $usuario->id]);
    expect(app(InvitacionService::class)->consultar($usuario->id, $token))->toBeNull();
    expect(fn () => app(InvitacionService::class)->activar($usuario->id, $token, ['password' => 'OtraClave12345!', 'passwordConfirmation' => 'OtraClave12345!']))
        ->toThrow(ValidationException::class, 'La invitación no es válida');
});

test('una invitación vencida no activa la cuenta', function (): void {
    Mail::fake();
    $this->freezeTime();
    administradorAccesos();
    app(UsuarioAccesoService::class)->crear(datosEmpleadoAccesos(), 'AdminSegura123!');
    [$usuario, $token] = ultimaInvitacionAccesos();
    Mail::assertSent(InvitacionEmpleado::class);
    Auth::logout();
    $this->travel(24)->hours();
    expect(app(InvitacionService::class)->consultar($usuario->id, $token))->toBeNull();
    expect(fn () => app(InvitacionService::class)->activar($usuario->id, $token, ['password' => 'OtraClave12345!', 'passwordConfirmation' => 'OtraClave12345!']))
        ->toThrow(ValidationException::class, 'La invitación no es válida');
    expect($usuario->fresh()->estado)->toBeFalse();
});

test('reenviar invalida el enlace anterior y revocar invalida el nuevo', function (): void {
    Mail::fake();
    administradorAccesos();
    $service = app(UsuarioAccesoService::class);
    $service->crear(datosEmpleadoAccesos(), 'AdminSegura123!');
    [$usuario, $token] = ultimaInvitacionAccesos();
    $service->ejecutarAccion($usuario->id, 'reenviar', 'AdminSegura123!');
    [, $nuevo] = ultimaInvitacionAccesos();
    Mail::assertSent(InvitacionEmpleado::class, 2);
    expect($nuevo)->not->toBe($token);
    expect(app(InvitacionService::class)->consultar($usuario->id, $token))->toBeNull();
    expect(app(InvitacionService::class)->consultar($usuario->id, $nuevo))->not->toBeNull();
    expect(fn () => $service->ejecutarAccion($usuario->id, 'reenviar', 'AdminSegura123!'))->toThrow(ValidationException::class, 'Espera un minuto');
    $service->ejecutarAccion($usuario->id, 'revocar', 'AdminSegura123!');
    expect(app(InvitacionService::class)->consultar($usuario->id, $nuevo))->toBeNull();
    expect($usuario->fresh()->estado)->toBeFalse();
});

test('no activa una invitación con un token alterado o de otra cuenta', function (): void {
    Mail::fake();
    administradorAccesos();
    app(UsuarioAccesoService::class)->crear(datosEmpleadoAccesos(), 'AdminSegura123!');
    [$usuario, $token] = ultimaInvitacionAccesos();
    Mail::assertSent(InvitacionEmpleado::class);
    Auth::logout();
    $service = app(InvitacionService::class);
    expect($service->consultar($usuario->id, str_repeat('0', 64)))->toBeNull();
    expect($service->consultar($usuario->id + 1, $token))->toBeNull();
    expect(fn () => $service->activar($usuario->id, str_repeat('0', 64), ['password' => 'OtraClave12345!', 'passwordConfirmation' => 'OtraClave12345!']))
        ->toThrow(ValidationException::class, 'La invitación no es válida');
});

test('valida la nueva contraseña en español', function (string $password, string $confirmacion, string $campo): void {
    Mail::fake();
    administradorAccesos();
    app(UsuarioAccesoService::class)->crear(datosEmpleadoAccesos(), 'AdminSegura123!');
    [$usuario, $token] = ultimaInvitacionAccesos();
    Mail::assertSent(InvitacionEmpleado::class);
    Auth::logout();
    Livewire::test(ActivarCuenta::class, ['usuario' => $usuario->id, 'token' => $token])
        ->set('password', $password)->set('passwordConfirmation', $confirmacion)->call('activar')
        ->assertHasErrors([$campo])->assertSet('password', '');
    expect($usuario->fresh()->estado)->toBeFalse();
})->with([
    ['', '', 'password'],
    ['Corta12!', 'Corta12!', 'password'],
    ['solominusculas12!', 'solominusculas12!', 'password'],
    ['SOLOMAYUSCULAS12!', 'SOLOMAYUSCULAS12!', 'password'],
    ['SinNumerosClave!', 'SinNumerosClave!', 'password'],
    ['SinSimbolosClave12', 'SinSimbolosClave12', 'password'],
    ['CorrectaClave12!', 'OtraClave12!', 'passwordConfirmation'],
    [str_repeat('á', 40).'Aa1!', str_repeat('á', 40).'Aa1!', 'password'],
]);

test('no permite activar mediante el botón de reactivar ni cambiar el rol de una invitación pendiente', function (): void {
    Mail::fake();
    administradorAccesos();
    $service = app(UsuarioAccesoService::class);
    $service->crear(datosEmpleadoAccesos(), 'AdminSegura123!');
    [$usuario] = ultimaInvitacionAccesos();
    Mail::assertSent(InvitacionEmpleado::class);
    expect(fn () => $service->ejecutarAccion($usuario->id, 'reactivar', 'AdminSegura123!'))->toThrow(ValidationException::class, 'La cuenta debe activarse desde el correo.');
    expect(fn () => $service->actualizar($usuario->id, datosEmpleadoAccesos(['rolUsuario' => 'Administrador']), 'AdminSegura123!'))->toThrow(ValidationException::class, 'La cuenta debe activarse antes de cambiar su rol.');
});

test('no escribe invitaciones ni tokens en el mailer log', function (): void {
    Mail::fake();
    administradorAccesos();
    config(['mail.default' => 'log']);
    expect(app(UsuarioAccesoService::class)->crear(datosEmpleadoAccesos(), 'AdminSegura123!'))->toBeFalse();
    Mail::assertNothingSent();
    $this->assertDatabaseHas('users', ['email' => 'ana@example.com', 'estado' => false]);
    $this->assertDatabaseHas('auditorias', ['accion' => 'invitacion_envio_fallido']);
});

test('un error smtp conserva una cuenta pendiente y permite reintentar', function (): void {
    administradorAccesos();
    config(['mail.default' => 'smtp']);
    Mail::shouldReceive('to')->once()->with('ana@example.com')
        ->andThrow(new TransportException('SMTP no disponible'));
    expect(app(UsuarioAccesoService::class)->crear(datosEmpleadoAccesos(), 'AdminSegura123!'))->toBeFalse();
    $this->assertDatabaseHas('users', ['email' => 'ana@example.com', 'estado' => false, 'invitacion_enviada_at' => null]);
    $this->assertDatabaseHas('auditorias', ['accion' => 'invitacion_envio_fallido']);
});

test('el correo se personaliza por rol y escapa el nombre', function (string $rol, string $contenido): void {
    $mail = new InvitacionEmpleado('<script>alert(1)</script>', 'ana@example.com', $rol, 'http://localhost:8000/activar-cuenta/10/'.str_repeat('a', 64));
    $mail->assertSeeInHtml($contenido)->assertSeeInText('ana@example.com');
    expect($mail->render())->not->toContain('<script>alert(1)</script>');
})->with([
    ['Administrador', 'administrar el sistema'],
    ['Inventario', 'productos e inventario'],
    ['Logistica', 'consultar productos y gestionar inventario'],
    ['Vendedor', 'consultar el catálogo interno'],
]);

test('el enlace usa APP_URL y no el host enviado por el visitante', function (): void {
    Mail::fake();
    administradorAccesos();
    config(['app.url' => 'http://localhost:8000']);
    app(UsuarioAccesoService::class)->crear(datosEmpleadoAccesos(), 'AdminSegura123!');
    Mail::assertSent(InvitacionEmpleado::class, fn ($mail) => str_starts_with($mail->urlActivacion, 'http://localhost:8000/activar-cuenta/'));
});
