<?php

use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
});

test('guest sees the toy store welcome page', function (): void {
    $this->get(route('welcome'))
        ->assertOk()
        ->assertSee('Explorar como invitado')
        ->assertSee('Aún no hay juguetes publicados');
});

test('registration validates fields and creates a client account', function (): void {
    Livewire::test(Register::class)
        ->set('name', 'A')
        ->set('email', 'correo-invalido')
        ->set('telefono', '123')
        ->set('password', 'corta')
        ->set('passwordConfirmation', 'diferente')
        ->call('register')
        ->assertHasErrors(['name', 'apellidoPaterno', 'apellidoMaterno', 'telefono', 'email', 'password'])
        ->assertSee('El celular debe tener 8 dígitos y comenzar con 6 o 7.');

    Livewire::test(Register::class)
        ->set('name', 'Valentina Juega')
        ->set('apellidoMaterno', 'Flores')
        ->set('telefono', '+591 71234567')
        ->set('email', 'valentina@example.com')
        ->set('password', 'Contrasena9-segura')
        ->set('passwordConfirmation', 'Contrasena9-segura')
        ->call('register')
        ->assertRedirect(route('perfil'));

    $usuario = User::query()->where('email', 'valentina@example.com')->firstOrFail();

    expect($usuario->apellido_paterno)->toBeNull()
        ->and($usuario->apellido_materno)->toBe('Flores')
        ->and($usuario->telefono)->toBe('+59171234567')
        ->and($usuario->hasRole('Cliente'))->toBeTrue();
});

test('client login redirects to their profile', function (): void {
    $usuario = User::factory()->create([
        'email' => 'cliente@example.com',
        'password' => Hash::make('contrasena-segura'),
    ]);
    $usuario->assignRole('Cliente');

    Livewire::test(Login::class)
        ->set('email', 'cliente@example.com')
        ->set('password', 'contrasena-segura')
        ->call('login')
        ->assertRedirect(route('perfil'));
});
