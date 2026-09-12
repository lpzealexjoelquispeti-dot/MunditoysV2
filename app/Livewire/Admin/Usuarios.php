<?php

namespace App\Livewire\Admin;

use App\Models\Sucursal;
use App\Services\UsuarioAccesoService;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class Usuarios extends Component
{
    use WithPagination;

    public string $grupo = 'empleados';

    public string $buscar = '';

    public string $rol = '';

    public string $estado = 'todos';

    public string $orden = 'recientes';

    public bool $mostrarFormulario = false;

    public bool $mostrarDetalle = false;

    #[Locked]
    public ?int $registroId = null;

    #[Locked]
    public array $detalle = [];

    #[Locked]
    public array $historial = [];

    public string $name = '';

    public string $apellido_paterno = '';

    public string $apellido_materno = '';

    public string $telefono = '';

    public string $email = '';

    public string $rolUsuario = 'Vendedor';

    public ?int $sucursal_id = null;

    protected UsuarioAccesoService $service;

    public function boot(UsuarioAccesoService $service): void
    {
        $this->service = $service;
    }

    protected function rules(): array
    {
        return $this->service->reglasFormulario($this->registroId !== null) + $this->service->reglasFiltros();
    }

    protected function messages(): array
    {
        return $this->service->mensajes();
    }

    protected function validationAttributes(): array
    {
        return ['rolUsuario' => 'rol', 'grupo' => 'tipo de usuario', 'orden' => 'orden', 'sucursal_id' => 'sucursal'];
    }

    public function updated(string $property): void
    {
        if (array_key_exists($property, $this->service->reglasFiltros())) {
            $this->resetPage();
            if ($property === 'grupo') {
                $this->rol = '';
            }
        }
        $this->validateOnly($property);
    }

    public function crear(): void
    {
        $this->cerrarFormulario();
        $this->mostrarFormulario = true;
    }

    public function editar(int $id): void
    {
        $datos = $this->service->obtener($id);
        if (($datos['rolUsuario'] ?? '') === 'Administrador' && $id !== (int) auth()->id()) {
            $this->notificar('Acción no permitida', 'Un administrador no puede editar los datos de otro administrador.', 'warning');

            return;
        }

        $this->cerrarFormulario();
        $this->registroId = $id;
        foreach (array_keys($this->service->reglasFormulario(true)) as $campo) {
            if ($campo === 'sucursal_id') {
                $this->sucursal_id = ! empty($datos['sucursal_id']) ? (int) $datos['sucursal_id'] : null;
            } else {
                $this->{$campo} = (string) ($datos[$campo] ?? '');
            }
        }
        $this->mostrarFormulario = true;
    }

    public function validarFormulario(): bool
    {
        try {
            $this->validate($this->service->reglasFormulario($this->registroId !== null));
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return false;
        }

        return true;
    }

    public function guardar(string $passwordAdministrador): void
    {
        try {
            $datos = $this->validate($this->service->reglasFormulario($this->registroId !== null));
            $enviado = true;
            $esNuevo = $this->registroId === null;
            if ($esNuevo) {
                $enviado = $this->service->crear($datos, $passwordAdministrador);
            } else {
                $this->service->actualizar($this->registroId, $datos, $passwordAdministrador);
            }
            $this->cerrarFormulario();
            $this->resetPage();
            $this->notificar($enviado ? 'Usuario guardado' : 'Cuenta pendiente; correo no enviado',
                ! $enviado ? 'Revisa la configuración SMTP de Mailtrap en .env y usa Reenviar invitación. No se activó la cuenta.'
                    : ($esNuevo ? 'La invitación se entregó al servidor de correo. En Sandbox, revísala dentro de Mailtrap.' : 'Los cambios quedaron registrados en la auditoría.'),
                $enviado ? 'success' : 'warning');
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());
            $this->notificar('Revisa los datos', $exception->validator->errors()->first(), 'warning');
        } finally {
            unset($passwordAdministrador);
        }
    }

    public function confirmarAccion(string $accion, int $id, string $passwordAdministrador): void
    {
        try {
            $enviado = $this->service->ejecutarAccion($id, $accion, $passwordAdministrador);
            $this->resetValidation();
            $this->notificar($enviado ? 'Operación completada' : 'Correo no enviado',
                $enviado ? 'El cambio quedó registrado en la auditoría.' : 'La cuenta sigue pendiente. Revisa SMTP y vuelve a reenviar la invitación.',
                $enviado ? 'success' : 'warning');
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());
            $this->notificar('No se pudo completar', $exception->validator->errors()->first(), 'warning');
        } finally {
            unset($passwordAdministrador);
        }
    }

    public function verDetalle(int $id): void
    {
        $this->detalle = $this->service->obtener($id);
        $this->historial = $this->service->historial($id);
        $this->mostrarDetalle = true;
    }

    public function cerrarDetalle(): void
    {
        $this->reset('mostrarDetalle', 'detalle', 'historial');
    }

    public function cerrarFormulario(): void
    {
        $this->reset('mostrarFormulario', 'registroId', 'name', 'apellido_paterno', 'apellido_materno', 'telefono', 'email', 'rolUsuario', 'sucursal_id');
        $this->resetValidation();
    }

    public function limpiarFiltros(): void
    {
        $this->reset('buscar', 'rol', 'estado', 'orden');
        $this->resetPage();
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.admin.usuarios', [
            'usuarios' => $this->service->listar($this->only(array_keys($this->service->reglasFiltros()))),
            'roles' => UsuarioAccesoService::ROLES,
            'sucursales' => Sucursal::query()->where('estado', true)->orderBy('nombre')->get(['id', 'nombre', 'ciudad']),
        ]);
    }

    private function notificar(string $titulo, string $mensaje, string $icono): void
    {
        $this->dispatch('alerta-producto', titulo: $titulo, mensaje: $mensaje, icono: $icono);
    }
}
