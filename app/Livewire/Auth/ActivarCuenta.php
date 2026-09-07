<?php

namespace App\Livewire\Auth;

use App\Services\InvitacionService;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ActivarCuenta extends Component
{
    #[Locked]
    public int $usuario;

    #[Locked]
    public string $token;

    #[Locked]
    public ?array $invitacion = null;

    public string $password = '';

    public string $passwordConfirmation = '';

    protected InvitacionService $service;

    public function boot(InvitacionService $service): void
    {
        $this->service = $service;
    }

    public function mount(int $usuario, string $token): void
    {
        $this->usuario = $usuario;
        $this->token = $token;
        $this->invitacion = $this->service->consultar($usuario, $token);
    }

    protected function rules(): array
    {
        return $this->service->reglas();
    }

    protected function validationAttributes(): array
    {
        return ['passwordConfirmation' => 'confirmación de contraseña'];
    }

    public function updated(string $property): void
    {
        $this->validateOnly($property);
    }

    public function activar(): void
    {
        try {
            $this->service->activar($this->usuario, $this->token, $this->only(['password', 'passwordConfirmation']));
            session()->flash('status', '¡Cuenta activada! Ya puedes iniciar sesión con tu nueva contraseña.');
            $this->redirectRoute('login');
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());
        } finally {
            $this->reset('password', 'passwordConfirmation');
        }
    }

    public function render(): View
    {
        return view('livewire.auth.activar-cuenta');
    }
}
