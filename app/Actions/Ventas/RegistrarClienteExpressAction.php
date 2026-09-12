<?php

namespace App\Actions\Ventas;

use App\Models\User;
use App\Services\VentaPosService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RegistrarClienteExpressAction
{
    public function __construct(private VentaPosService $posService) {}

    /**
     * Registra un nuevo cliente desde el punto de venta de mostrador (POS).
     *
     * @param  array<string, mixed>  $datos
     */
    public function ejecutar(array $datos): User
    {
        $validador = Validator::make(
            $datos,
            $this->posService->reglasRegistroCliente(),
            $this->posService->mensajesRegistroCliente(),
            $this->posService->atributosValidacionCliente()
        );

        $validados = $validador->validate();

        $ciNit = trim($validados['ci_nit']);
        $email = ! empty($validados['email'])
            ? trim($validados['email'])
            : 'cliente_'.Str::slug($ciNit).'_'.Str::lower(Str::random(5)).'@munditoys.bo';

        $usuario = User::query()->create([
            'name' => trim($validados['name']),
            'apellido_paterno' => ! empty($validados['apellido_paterno']) ? trim($validados['apellido_paterno']) : null,
            'apellido_materno' => ! empty($validados['apellido_materno']) ? trim($validados['apellido_materno']) : null,
            'ci_nit' => $ciNit,
            'telefono' => trim($validados['telefono']),
            'email' => $email,
            'tipo_usuario' => 'cliente',
            'password' => Hash::make(Str::random(32)),
            'estado' => true,
        ]);

        $usuario->assignRole('Cliente');

        return $usuario;
    }
}
