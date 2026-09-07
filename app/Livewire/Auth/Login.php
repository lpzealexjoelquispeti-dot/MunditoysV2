<?php

namespace App\Livewire\Auth;

use App\Services\AutenticacionService;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    protected function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }

    public function updated(string $property): void
    {
        $this->validateOnly($property);
    }

    public function login(AutenticacionService $autenticacionService): void
    {
        $credenciales = $this->validate();

        try {
            $usuario = $autenticacionService->iniciarSesion($credenciales + ['remember' => $this->remember]);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return;
        }

        $this->reset('password');
        $this->redirectRoute($usuario->can('acceder panel') ? 'admin.dashboard' : 'perfil', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
