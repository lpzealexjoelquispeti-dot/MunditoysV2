<?php

namespace App\Services;

use App\Actions\Proveedores\ActualizarProveedorAction;
use App\Actions\Proveedores\CrearProveedorAction;
use App\Actions\Proveedores\EliminarProveedorAction;
use App\Actions\Proveedores\RestaurarProveedorAction;
use App\Models\Proveedor;
use App\Repositories\ProveedorRepository;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProveedorService
{
    public function __construct(
        private ProveedorRepository $repository,
        private CrearProveedorAction $crearAction,
        private ActualizarProveedorAction $actualizarAction,
        private EliminarProveedorAction $eliminarAction,
        private RestaurarProveedorAction $restaurarAction,
    ) {}

    /** @param array<string, mixed> $filtros */
    public function listar(array $filtros): LengthAwarePaginator
    {
        Gate::authorize('viewAny', Proveedor::class);

        return $this->repository->paginar($filtros);
    }

    /** @return array<string, mixed> */
    public function obtenerParaEditar(int $id): array
    {
        $registro = $this->repository->buscarPorId($id);
        Gate::authorize('update', $registro);

        return $this->detalle($registro);
    }

    /** @return array<string, mixed> */
    public function obtenerDetalle(int $id): array
    {
        $registro = $this->repository->buscarPorId($id, true);
        Gate::authorize('view', $registro);

        return $this->detalle($registro);
    }

    /** @param array<string, mixed> $datos
     * @return array<string, array<int, mixed>>
     */
    public function reglasFormulario(?int $id = null, array $datos = []): array
    {
        return [

            'empresa' => ['bail', 'required', 'string', 'min:3', 'max:150', "regex:/\A(?=.*[\pL\pN])[\pL\pM\pN .&'\-]+\z/u",
                function (string $attribute, mixed $value, Closure $fail) use ($id): void {
                    if ($this->repository->nombreExiste(Str::squish($value), $id)) {
                        $fail('Ya existe un proveedor con ese nombre, incluso en la papelera.');
                    }
                }],
            'nombre_contacto' => ['bail', 'required', 'string', 'min:3', 'max:120', 'regex:/\A[\pL\pM]+(?: [\pL\pM]+)*\z/u'],
            'telefono' => ['bail', 'required', 'string', 'regex:/\A(?:\+591 ?)?[2-7][0-9]{7}\z/'],
            'email' => ['bail', 'nullable', 'string', 'max:254', 'email:rfc'],
            'direccion' => ['bail', 'required', 'string', 'min:8', 'max:300', "regex:/\A(?=.*\pL)[\pL\pM\pN .,#º°\/()'\-]+\z/u"],
            'nit' => ['bail', 'nullable', 'string', 'regex:/\A[1-9][0-9]{4,19}\z/',
                function (string $attribute, mixed $value, Closure $fail) use ($id): void {
                    if ($this->repository->nitExiste($value, $id)) {
                        $fail('Este NIT ya está registrado, incluso en la papelera.');
                    }
                }],
            'estado' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function atributos(): array
    {
        return [
            'nombre' => 'nombre', 'empresa' => 'empresa', 'nombre_contacto' => 'nombre del contacto',
            'telefono' => 'teléfono', 'direccion' => 'dirección', 'nit' => 'NIT', 'email' => 'correo electrónico',
            'sucursal_id' => 'sucursal', 'tipo' => 'tipo de almacén', 'estado' => 'estado',
            'filtroSucursal' => 'filtro de sucursal', 'filtroTipo' => 'filtro de tipo', 'filtroEstado' => 'filtro de estado',
        ];
    }

    /** @return array<string, string> */
    public function mensajes(): array
    {
        return [
            'nombre.regex' => 'Usa letras, números y espacios, por ejemplo: Almacén 1. No se admiten signos ni nombres formados solo por números.',
            'empresa.regex' => 'Usa letras o números; solo se permiten espacios, puntos, guiones, apóstrofes y & como separadores.',
            'nombre_contacto.regex' => 'El nombre del contacto solo admite letras y espacios.',
            'telefono.regex' => 'Introduce un teléfono boliviano de 8 dígitos que comience entre 2 y 7. El prefijo +591 es opcional.',
            'direccion.regex' => 'Escribe una dirección con letras y números; solo admite puntuación habitual de direcciones.',
            'nit.regex' => 'El NIT debe contener entre 5 y 20 dígitos y no comenzar con cero.',
            'sucursal_id.exists' => 'La sucursal seleccionada ya no está disponible.',
            'tipo.in' => 'Selecciona un tipo de almacén válido.',
        ];
    }

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): Proveedor
    {
        Gate::authorize('create', Proveedor::class);
        $datos['estado'] = true;
        $datos = $this->validar($datos);
        try {
            return DB::transaction(function () use ($datos): Proveedor {
                return $this->crearAction->ejecutar($datos);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['empresa' => 'Ya existe un proveedor con ese nombre o NIT. Revisa también la papelera.']);
        }
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(int $id, array $datos): Proveedor
    {
        Gate::authorize('update', $this->repository->buscarPorId($id));
        $datos = $this->validar($datos, $id);
        try {
            return DB::transaction(function () use ($id, $datos): Proveedor {
                $registro = $this->repository->buscarPorId($id, false, true);
                Gate::authorize('update', $registro);

                return $this->actualizarAction->ejecutar($registro, $datos);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['empresa' => 'Ya existe un proveedor con ese nombre o NIT. Revisa también la papelera.']);
        }
    }

    public function eliminar(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $registro = $this->repository->buscarPorId($id, false, true);
            Gate::authorize('delete', $registro);
            $this->eliminarAction->ejecutar($registro);
        });
    }

    public function restaurar(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $registro = $this->repository->buscarPorId($id, true, true);
            Gate::authorize('restore', $registro);
            $this->restaurarAction->ejecutar($registro);
        });
    }

    /** @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function validar(array $datos, ?int $id = null): array
    {
        foreach (['empresa', 'nombre_contacto', 'telefono', 'email', 'direccion', 'nit'] as $campo) {
            if (isset($datos[$campo]) && is_string($datos[$campo])) {
                $datos[$campo] = Str::squish($datos[$campo]);
            }
        }
        $validado = Validator::make($datos, $this->reglasFormulario($id, $datos), $this->mensajes(), $this->atributos())->validate();
        $validado['telefono'] = preg_replace('/^\\+591 ?/', '', $validado['telefono']);
        $validado['email'] = empty($validado['email']) ? null : Str::lower($validado['email']);
        $validado['nit'] = ($validado['nit'] ?? '') ?: null;

        return Arr::only($validado, ['empresa', 'nombre_contacto', 'telefono', 'email', 'direccion', 'nit', 'estado']);
    }

    /** @return array<string, mixed> */
    private function detalle(Proveedor $registro): array
    {
        return $registro->only(['empresa', 'nombre_contacto', 'telefono', 'email', 'direccion', 'nit', 'id', 'estado']) + [
            'eliminado' => $registro->trashed(), 'creado' => $registro->created_at?->format('d/m/Y'),
            'asociados' => $registro->lotes_count,
        ];
    }
}
