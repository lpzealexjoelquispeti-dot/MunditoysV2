<?php

namespace App\Livewire\Auth;

use App\Services\AutenticacionService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Component;

class Register extends Component
{
    public string $name = '';

    public string $apellidoPaterno = '';

    public string $apellidoMaterno = '';

    public string $telefono = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    protected function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'min:2', 'max:100', "regex:/^[\pL\pM' -]+$/u"],
            'apellidoPaterno' => ['bail', 'required_without:apellidoMaterno', 'nullable', 'string', 'min:2', 'max:100', "regex:/^[\pL\pM' -]+$/u"],
            'apellidoMaterno' => ['bail', 'required_without:apellidoPaterno', 'nullable', 'string', 'min:2', 'max:100', "regex:/^[\pL\pM' -]+$/u"],
            'telefono' => ['required', 'string', 'regex:/^(?:\+591[\s-]?)?[67]\d{7}$/'],
            'email' => ['bail', 'required', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', Password::min(8)->letters()->mixedCase()->numbers()],
            'passwordConfirmation' => ['required', 'string', 'same:password'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'nombre',
            'apellidoPaterno' => 'apellido paterno',
            'apellidoMaterno' => 'apellido materno',
            'telefono' => 'celular',
            'passwordConfirmation' => 'confirmación de contraseña',
        ];
    }

    protected function messages(): array
    {
        return [
            'apellidoPaterno.required_without' => 'Escribe al menos uno de tus apellidos.',
            'apellidoMaterno.required_without' => 'Escribe al menos uno de tus apellidos.',
            'name.regex' => 'El nombre solo puede contener letras, espacios, apóstrofes y guiones.',
            'apellidoPaterno.regex' => 'El apellido paterno solo puede contener letras, espacios, apóstrofes y guiones.',
            'apellidoMaterno.regex' => 'El apellido materno solo puede contener letras, espacios, apóstrofes y guiones.',
            'telefono.regex' => 'El celular debe tener 8 dígitos y comenzar con 6 o 7. Puedes incluir el prefijo +591.',
        ];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['apellidoPaterno', 'apellidoMaterno'], true)) {
            $this->validateOnly('apellidoPaterno');
            $this->validateOnly('apellidoMaterno');

            return;
        }

        $this->validateOnly($property);
    }

    public function register(AutenticacionService $autenticacionService): void
    {
        $atributos = $this->validate();
        $autenticacionService->registrar([
            'name' => $atributos['name'],
            'apellido_paterno' => $atributos['apellidoPaterno'] ?: null,
            'apellido_materno' => $atributos['apellidoMaterno'] ?: null,
            'telefono' => $atributos['telefono'],
            'email' => $atributos['email'],
            'password' => $atributos['password'],
        ]);

        $this->redirectRoute('perfil', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.register');
    }
}
