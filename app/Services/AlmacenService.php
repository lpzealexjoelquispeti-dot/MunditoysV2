<?php

namespace App\Services;

use App\Actions\Almacenes\ActualizarAlmacenAction;
use App\Actions\Almacenes\CrearAlmacenAction;
use App\Actions\Almacenes\EliminarAlmacenAction;
use App\Actions\Almacenes\RestaurarAlmacenAction;
use App\Models\Almacen;
use App\Repositories\AlmacenRepository;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AlmacenService
{
    public function __construct(
        private AlmacenRepository $repository,
        private CrearAlmacenAction $crearAction,
        private ActualizarAlmacenAction $actualizarAction,
        private EliminarAlmacenAction $eliminarAction,
        private RestaurarAlmacenAction $restaurarAction,
    ) {}

    /** @param array<string, mixed> $filtros */
    public function listar(array $filtros): LengthAwarePaginator
    {
        Gate::authorize('viewAny', Almacen::class);

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

            'nombre' => ['bail', 'required', 'string', 'min:3', 'max:100', 'regex:/\A(?=.*\pL)[\pL\pM\pN]+(?: [\pL\pM\pN]+)*\z/u',
                function (string $attribute, mixed $value, Closure $fail) use ($id, $datos): void {
                    if ($this->repository->nombreExiste(Str::squish($value), $id, (int) ($datos['sucursal_id'] ?? 0))) {
                        $fail('Ya existe un almacén con ese nombre en esta sucursal, incluso en la papelera.');
                    }
                }],
            'sucursal_id' => ['bail', 'required', 'integer', Rule::exists('sucursales', 'id')->whereNull('deleted_at')],
            'tipo' => ['required', Rule::in(['principal', 'secundario'])],
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
    public function crear(array $datos): Almacen
    {
        Gate::authorize('create', Almacen::class);
        $datos['estado'] = true;
        $datos = $this->validar($datos);
        try {
            return DB::transaction(function () use ($datos): Almacen {
                $this->exigirSucursalActiva((int) $datos['sucursal_id']);

                return $this->crearAction->ejecutar($datos);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe un almacén con ese nombre en esta sucursal. Revisa también la papelera.']);
        }
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(int $id, array $datos): Almacen
    {
        Gate::authorize('update', $this->repository->buscarPorId($id));
        $datos = $this->validar($datos, $id);
        try {
            return DB::transaction(function () use ($id, $datos): Almacen {
                if ($datos['estado']) {
                    $this->exigirSucursalActiva((int) $datos['sucursal_id']);
                }
                $registro = $this->repository->buscarPorId($id, false, true);
                Gate::authorize('update', $registro);
                if ((int) $registro->sucursal_id !== (int) $datos['sucursal_id'] && $this->repository->tieneHistorial($registro)) {
                    throw ValidationException::withMessages(['sucursal_id' => 'No puedes cambiar de sucursal un almacén con historial de inventario. Crea otro almacén y utiliza un traslado.']);
                }
                if (! $datos['estado']) {
                    $this->verificarBaja($registro);
                }

                return $this->actualizarAction->ejecutar($registro, $datos);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe un almacén con ese nombre en esta sucursal. Revisa también la papelera.']);
        }
    }

    public function eliminar(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $registro = $this->repository->buscarPorId($id, false, true);
            Gate::authorize('delete', $registro);
            $this->verificarBaja($registro);
            $this->eliminarAction->ejecutar($registro);
        });
    }

    public function restaurar(int $id): void
    {
        DB::transaction(function () use ($id): void {
            $anterior = $this->repository->buscarPorId($id, true);
            Gate::authorize('restore', $anterior);
            $this->exigirSucursalActiva((int) $anterior->sucursal_id);
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
        foreach (['nombre', 'sucursal_id', 'tipo'] as $campo) {
            if (isset($datos[$campo]) && is_string($datos[$campo])) {
                $datos[$campo] = Str::squish($datos[$campo]);
            }
        }
        $validado = Validator::make($datos, $this->reglasFormulario($id, $datos), $this->mensajes(), $this->atributos())->validate();

        return Arr::only($validado, ['nombre', 'sucursal_id', 'tipo', 'estado']);
    }

    /** @return array<string, mixed> */
    private function detalle(Almacen $registro): array
    {
        return $registro->only(['nombre', 'sucursal_id', 'tipo', 'id', 'estado']) + [
            'eliminado' => $registro->trashed(), 'creado' => $registro->created_at?->format('d/m/Y'),
            'asociados' => $registro->inventarios_count,
            'sucursal' => $registro->sucursal?->nombre ?? 'Sucursal no disponible',
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function sucursales(): array
    {
        Gate::authorize('viewAny', Almacen::class);

        return $this->repository->sucursales();
    }

    private function verificarBaja(Almacen $registro): void
    {
        if ($this->repository->tieneExistencias($registro)) {
            throw ValidationException::withMessages(['estado' => 'El almacén todavía tiene existencias. Registra sus salidas o traslados antes de desactivarlo o enviarlo a la papelera.']);
        }
    }

    private function exigirSucursalActiva(int $id): void
    {
        $sucursal = $this->repository->bloquearSucursal($id);
        if (! $sucursal || ! $sucursal->estado) {
            throw ValidationException::withMessages(['sucursal_id' => 'Selecciona una sucursal activa.']);
        }
    }
}
