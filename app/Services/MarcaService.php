<?php

namespace App\Services;

use App\Actions\Marcas\ActualizarMarcaAction;
use App\Actions\Marcas\CrearMarcaAction;
use App\Actions\Marcas\EliminarMarcaAction;
use App\Actions\Marcas\RestaurarMarcaAction;
use App\Models\Marca;
use App\Repositories\MarcaRepository;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarcaService
{
    public function __construct(
        private MarcaRepository $repository,
        private CrearMarcaAction $crearAction,
        private ActualizarMarcaAction $actualizarAction,
        private EliminarMarcaAction $eliminarAction,
        private RestaurarMarcaAction $restaurarAction,
    ) {}

    /** @param array<string, mixed> $filtros */
    public function listar(array $filtros, int $porPagina = 12): LengthAwarePaginator
    {
        Gate::authorize('viewAny', Marca::class);

        return $this->repository->paginar($filtros, $porPagina);
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

    /** @return array<string, array<int, mixed>> */
    public function reglasFormulario(?int $id = null, bool $tieneImagen = false): array
    {
        return [
            'nombre' => ['bail', 'required', 'string', 'min:3', 'max:100', "regex:/^[\\pL\\pN][\\pL\\pM\\pN .&'\\-]*$/u",
                function (string $attribute, mixed $value, Closure $fail) use ($id): void {
                    if ($this->repository->nombreExiste(Str::squish($value), $id)) {
                        $fail('Ya existe una marca con ese nombre, incluso en la papelera.');
                    }
                }],
            'descripcion' => ['bail', 'required', 'string', 'min:10', 'max:1000'],
            'imagen' => ['bail', $tieneImagen ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:3072'],
            'estado' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function mensajes(): array
    {
        return [
            'nombre.regex' => 'El nombre admite letras, números, espacios, puntos, guiones, apóstrofes y &.',
            'imagen.required' => 'Selecciona una imagen para la marca.',
            'imagen.image' => 'El archivo debe ser una imagen válida.',
            'imagen.mimes' => 'Solo se admiten imágenes JPG, PNG o WEBP.',
            'imagen.max' => 'La imagen debe pesar como máximo 3 MB.',
        ];
    }

    /** @param array<string, mixed> $datos */
    public function crear(array $datos, ?UploadedFile $imagen = null): Marca
    {
        Gate::authorize('create', Marca::class);
        $datos = $this->validar($datos, $imagen);
        $datos['estado'] = true;

        try {
            return $this->crearAction->ejecutar($datos, $imagen);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe una marca con ese nombre. Revisa también la papelera.']);
        }
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(int $id, array $datos, ?UploadedFile $imagen = null): Marca
    {
        $registro = $this->repository->buscarPorId($id);
        Gate::authorize('update', $registro);
        $datos = $this->validar($datos, $imagen, $registro);
        if (! $datos['estado'] && $this->repository->tieneProductos($registro)) {
            throw ValidationException::withMessages(['estado' => 'No puedes desactivar una marca con productos asociados.']);
        }

        try {
            return $this->actualizarAction->ejecutar($registro, $datos, $imagen);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['nombre' => 'Ya existe una marca con ese nombre. Revisa también la papelera.']);
        }
    }

    public function eliminar(int $id): bool
    {
        $registro = $this->repository->buscarPorId($id);
        Gate::authorize('delete', $registro);
        if ($this->repository->tieneProductos($registro)) {
            throw ValidationException::withMessages(['operacion' => 'Esta marca tiene productos asociados. Reasígnalos antes de enviarla a la papelera.']);
        }

        return $this->eliminarAction->ejecutar($registro);
    }

    public function restaurar(int $id): bool
    {
        $registro = $this->repository->buscarPorId($id, true);
        Gate::authorize('restore', $registro);

        return $this->restaurarAction->ejecutar($registro);
    }

    /** @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    private function validar(array $datos, ?UploadedFile $imagen, ?Marca $registro = null): array
    {
        $datos['nombre'] = Str::squish((string) ($datos['nombre'] ?? ''));
        $datos['descripcion'] = trim((string) ($datos['descripcion'] ?? ''));
        $datos['imagen'] = $imagen;
        $datos['estado'] ??= true;
        $validado = Validator::make($datos, $this->reglasFormulario($registro?->id, (bool) $registro?->imagen), $this->mensajes())->validate();

        return Arr::only($validado, ['nombre', 'descripcion', 'estado']);
    }

    /** @return array<string, mixed> */
    private function detalle(Marca $registro): array
    {
        return [
            'id' => $registro->id, 'nombre' => $registro->nombre,
            'descripcion' => $registro->descripcion ?? '', 'estado' => $registro->estado,
            'imagen' => $registro->imagen, 'eliminado' => $registro->trashed(),
            'productos' => $registro->productos_count, 'creada' => $registro->created_at?->format('d/m/Y'),
        ];
    }
}
