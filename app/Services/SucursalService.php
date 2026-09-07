<?php

namespace App\Services;

use App\Actions\Sucursales\ActualizarSucursalAction;
use App\Actions\Sucursales\CrearSucursalAction;
use App\Actions\Sucursales\EliminarSucursalAction;
use App\Actions\Sucursales\RestaurarSucursalAction;
use App\Models\Sucursal;
use App\Repositories\SucursalRepository;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SucursalService
{
    public function __construct(
        private SucursalRepository $repository,
        private UbicacionLaPazService $ubicacion,
        private CrearSucursalAction $crearAction,
        private ActualizarSucursalAction $actualizarAction,
        private EliminarSucursalAction $eliminarAction,
        private RestaurarSucursalAction $restaurarAction,
    ) {}

    /** @param array<string, mixed> $filtros */
    public function listar(array $filtros): LengthAwarePaginator
    {
        Gate::authorize('viewAny', Sucursal::class);

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
        return array_merge([
            'nombre' => ['bail', 'required', 'string', 'min:3', 'max:100', 'regex:/\A(?=.*\pL)[\pL\pM\pN]+(?: [\pL\pM\pN]+)*\z/u',
                function (string $attribute, mixed $value, Closure $fail) use ($id): void {
                    if ($this->repository->nombreExiste(Str::squish($value), $id)) {
                        $fail('Ya existe una sucursal con ese nombre, incluso en la papelera.');
                    }
                }],
            'direccion' => ['bail', 'required', 'string', 'min:8', 'max:300', "regex:/\A(?=.*\pL)[\pL\pM\pN .,#º°\/()'\-]+\z/u"],
            'zona' => ['bail', 'required', 'string', 'min:3', 'max:100', 'regex:/\A(?=.*\pL)[\pL\pM\pN]+(?: [\pL\pM\pN]+)*\z/u'],
            'telefono' => ['bail', 'required', 'string', 'regex:/\A(?:\+591 ?)?[2-7][0-9]{7}\z/'],
            'estado' => ['required', 'boolean'],
        ], $this->ubicacion->reglas());
    }

    /** @return array<string, string> */
    public function atributos(): array
    {
        return [
            'nombre' => 'nombre', 'direccion' => 'dirección', 'zona' => 'zona',
            'telefono' => 'teléfono', 'latitud' => 'latitud', 'longitud' => 'longitud',
            'estado' => 'estado', 'filtroEstado' => 'filtro de estado',
        ];
    }

    /** @return array<string, string> */
    public function mensajes(): array
    {
        return [
            'nombre.regex' => 'El nombre admite letras, números y espacios; debe contener al menos una letra.',
            'zona.regex' => 'La zona admite letras, números y espacios; debe contener al menos una letra.',
            'direccion.regex' => 'Escribe una dirección con letras y números; solo admite puntuación habitual de direcciones.',
            'telefono.regex' => 'Introduce un teléfono boliviano de 8 dígitos que comience entre 2 y 7. El prefijo +591 es opcional.',
            'latitud.required' => 'Selecciona la ubicación de la sucursal en el mapa.',
            'longitud.required' => 'Selecciona la ubicación de la sucursal en el mapa.',
            'latitud.regex' => 'La latitud debe tener como máximo 7 decimales.',
            'longitud.regex' => 'La longitud debe tener como máximo 7 decimales.',
        ];
    }

    public function validarUbicacion(string $latitud, string $longitud): void
    {
        Gate::authorize('viewAny', Sucursal::class);
        $this->ubicacion->validar($latitud, $longitud);
    }

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): Sucursal
    {
        Gate::authorize('create', Sucursal::class);
        $datos['estado'] = true;
        $datos = $this->validar($datos);
        try {
            return DB::transaction(fn (): Sucursal => $this->crearAction->ejecutar($datos));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe una sucursal con ese nombre. Revisa también la papelera.']);
        }
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(int $id, array $datos): Sucursal
    {
        Gate::authorize('update', $this->repository->buscarPorId($id));
        $datos = $this->validar($datos, $id);
        try {
            return DB::transaction(function () use ($id, $datos): Sucursal {
                $registro = $this->repository->buscarPorId($id, false, true);
                Gate::authorize('update', $registro);
                if (! $datos['estado']) {
                    $this->verificarBaja($registro);
                }

                return $this->actualizarAction->ejecutar($registro, $datos);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe una sucursal con ese nombre. Revisa también la papelera.']);
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
            $registro = $this->repository->buscarPorId($id, true, true);
            Gate::authorize('restore', $registro);
            $this->restaurarAction->ejecutar($registro);
        });
    }

    private function verificarBaja(Sucursal $registro): void
    {
        if ($this->repository->tieneDependenciasActivas($registro)) {
            throw ValidationException::withMessages(['estado' => 'La sucursal tiene almacenes activos, existencias o entregas pendientes. Resuelve esas operaciones antes de darla de baja.']);
        }
    }

    /** @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function validar(array $datos, ?int $id = null): array
    {
        foreach (['nombre', 'direccion', 'zona', 'telefono'] as $campo) {
            if (isset($datos[$campo]) && is_string($datos[$campo])) {
                $datos[$campo] = Str::squish($datos[$campo]);
            }
        }
        $validado = Validator::make($datos, $this->reglasFormulario($id), $this->mensajes(), $this->atributos())->validate();
        $this->ubicacion->validar((string) $validado['latitud'], (string) $validado['longitud']);
        $datos = Arr::only($validado, ['nombre', 'direccion', 'zona', 'telefono', 'latitud', 'longitud', 'estado']);
        $datos['ciudad'] = 'La Paz';
        $datos['telefono'] = preg_replace('/^\+591 ?/', '', $datos['telefono']);
        $datos['ubicacion_jsonb'] = [
            'type' => 'Point', 'coordinates' => [(float) $datos['longitud'], (float) $datos['latitud']],
            'municipio' => 'Nuestra Señora de La Paz', 'limite' => 'BOL-ADM3-33444609', 'anio_referencia' => 2015,
        ];

        return $datos;
    }

    /** @return array<string, mixed> */
    private function detalle(Sucursal $registro): array
    {
        return $registro->only(['id', 'nombre', 'direccion', 'zona', 'telefono', 'ciudad', 'latitud', 'longitud', 'estado']) + [
            'eliminado' => $registro->trashed(), 'creado' => $registro->created_at?->format('d/m/Y'),
            'asociados' => $registro->almacenes_count,
        ];
    }
}
