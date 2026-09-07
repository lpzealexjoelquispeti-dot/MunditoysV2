<?php

namespace App\Livewire\Cliente;

use App\Services\PerfilService;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class Profile extends Component
{
    use WithFileUploads;

    public string $name = '';

    public ?string $apellidoPaterno = null;

    public ?string $apellidoMaterno = null;

    public ?string $telefono = null;

    public ?string $ciNit = null;

    public string $email = '';

    #[Validate(['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'])]
    public $foto = null;

    public ?string $fotoActual = null;

    public function mount(PerfilService $perfilService): void
    {
        $usuario = $perfilService->obtener((int) auth()->id());
        $this->name = $usuario['name'];
        $this->apellidoPaterno = $usuario['apellido_paterno'];
        $this->apellidoMaterno = $usuario['apellido_materno'];
        $this->telefono = $usuario['telefono'];
        $this->ciNit = $usuario['ci_nit'];
        $this->email = $usuario['email'];
        $this->fotoActual = $usuario['foto'];
    }

    protected function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'min:2', 'max:100', "regex:/^[\pL\pM' -]+$/u"],
            'apellidoPaterno' => ['bail', 'required_without:apellidoMaterno', 'nullable', 'string', 'min:2', 'max:100', "regex:/^[\pL\pM' -]+$/u"],
            'apellidoMaterno' => ['bail', 'required_without:apellidoPaterno', 'nullable', 'string', 'min:2', 'max:100', "regex:/^[\pL\pM' -]+$/u"],
            'telefono' => ['required', 'string', 'regex:/^(?:\+591[\s-]?)?[67]\d{7}$/'],
            'ciNit' => ['nullable', 'string', 'min:5', 'max:20', 'regex:/^[A-Za-z0-9-]+$/'],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'name' => 'nombre',
            'apellidoPaterno' => 'apellido paterno',
            'apellidoMaterno' => 'apellido materno',
            'telefono' => 'celular',
            'ciNit' => 'CI o NIT',
            'foto' => 'foto de perfil',
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
            'ciNit.regex' => 'El CI o NIT solo puede contener letras, números y guiones.',
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

    public function guardar(PerfilService $perfilService): void
    {
        $atributos = $this->validate();

        $perfil = $perfilService->actualizar((int) auth()->id(), [
            'name' => $atributos['name'],
            'apellido_paterno' => $atributos['apellidoPaterno'],
            'apellido_materno' => $atributos['apellidoMaterno'],
            'telefono' => $atributos['telefono'],
            'ci_nit' => $atributos['ciNit'],
        ], $this->foto);

        $this->fotoActual = $perfil['foto'];
        $this->foto = null;
        session()->flash('status', '¡Tus datos ya quedaron guardados!');
    }

    public function render(): View
    {
        return view('livewire.cliente.profile');
    }
}
