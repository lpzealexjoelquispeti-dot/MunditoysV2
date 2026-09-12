<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class VentaPosService
{
    /**
     * @return Collection<int, Sucursal>
     */
    public function sucursalesActivas(): Collection
    {
        return Sucursal::query()
            ->where('estado', true)
            ->with(['almacenes' => fn ($query) => $query->where('estado', true)->orderBy('nombre')])
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Busca juguetes para el POS calculando stock en la sucursal actual y desglose en otras sucursales.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buscarJuguetesParaPos(string $termino, int $sucursalId, ?int $categoriaId = null): array
    {
        $sucursalesTodas = Sucursal::query()
            ->where('estado', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $consulta = Producto::query()
            ->where('estado', 'activo')
            ->with([
                'imagenes',
                'categoria',
                'marca',
                'modelos3D',
                'inventarios.almacen.sucursal',
                'inventarios.lotes' => fn ($q) => $q->where('estado', 'activo')->where('cantidad_disponible', '>', 0),
            ]);

        $termino = trim($termino);
        if ($termino !== '') {
            $consulta->where(function (Builder $q) use ($termino): void {
                $q->whereRaw('LOWER(nombre) LIKE ?', ['%'.Str::lower($termino).'%'])
                    ->orWhereRaw('LOWER(codigo_producto) LIKE ?', ['%'.Str::lower($termino).'%'])
                    ->orWhereRaw('LOWER(codigo_qr) LIKE ?', ['%'.Str::lower($termino).'%']);
            });
        }

        if ($categoriaId) {
            $consulta->where('categoria_id', $categoriaId);
        }

        $productos = $consulta->orderBy('nombre')->limit(24)->get();

        return $productos->map(function (Producto $prod) use ($sucursalId, $sucursalesTodas): array {
            $stockPorSucursal = [];
            foreach ($sucursalesTodas as $suc) {
                $stockPorSucursal[$suc->id] = 0;
            }

            $almacenesSucursalActual = [];
            foreach ($prod->inventarios as $inv) {
                $almacen = $inv->almacen;
                if (! $almacen || ! $almacen->sucursal_id) {
                    continue;
                }

                $sucId = (int) $almacen->sucursal_id;
                $cant = (int) $inv->lotes->sum('cantidad_disponible');

                if (isset($stockPorSucursal[$sucId])) {
                    $stockPorSucursal[$sucId] += $cant;
                }

                if ($sucId === $sucursalId) {
                    $almacenesSucursalActual[$inv->almacen_id] = [
                        'inventario_id' => $inv->id,
                        'almacen_id' => $inv->almacen_id,
                        'almacen_nombre' => $almacen->nombre,
                        'disponible' => $cant,
                    ];
                }
            }

            $stockActual = $stockPorSucursal[$sucursalId] ?? 0;

            $desgloseOtrasSucursales = [];
            foreach ($sucursalesTodas as $suc) {
                if ($suc->id !== $sucursalId) {
                    $desgloseOtrasSucursales[] = [
                        'sucursal_id' => $suc->id,
                        'sucursal_nombre' => $suc->nombre,
                        'stock' => $stockPorSucursal[$suc->id] ?? 0,
                    ];
                }
            }

            $primeraImagen = $prod->imagenes->sortBy('orden')->first();
            $tieneModelado3D = $prod->modelos3D->whereIn('status', ['success', 'completed'])->isNotEmpty();

            $precioVenta = (float) ($prod->precio_venta ?? 0);
            if ($precioVenta <= 0) {
                $primerLote = $prod->inventarios->flatMap->lotes->first();
                if ($primerLote && $primerLote->precio_venta) {
                    $precioVenta = (float) $primerLote->precio_venta;
                }
            }

            return [
                'id' => $prod->id,
                'nombre' => $prod->nombre,
                'codigo_producto' => $prod->codigo_producto,
                'codigo_qr' => $prod->codigo_qr,
                'categoria' => $prod->categoria?->nombre ?? 'Sin categoría',
                'marca' => $prod->marca?->nombre ?? 'Sin marca',
                'precio_venta' => $precioVenta,
                'imagen_url' => $primeraImagen ? asset('storage/'.$primeraImagen->ruta_archivo) : null,
                'tiene_modelado_3d' => $tieneModelado3D,
                'stock_sucursal_actual' => $stockActual,
                'almacenes_sucursal_actual' => array_values($almacenesSucursalActual),
                'desglose_otras_sucursales' => $desgloseOtrasSucursales,
            ];
        })->all();
    }

    /**
     * Búsqueda ágil de clientes por CI/NIT, teléfono o nombre.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buscarClientes(string $termino): array
    {
        $termino = trim($termino);
        if ($termino === '') {
            return [];
        }

        $usuarios = User::query()
            ->where(function (Builder $query) use ($termino): void {
                $query->whereRaw('LOWER(ci_nit) LIKE ?', ['%'.Str::lower($termino).'%'])
                    ->orWhereRaw('LOWER(telefono) LIKE ?', ['%'.Str::lower($termino).'%'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%'.Str::lower($termino).'%'])
                    ->orWhereRaw('LOWER(apellido_paterno) LIKE ?', ['%'.Str::lower($termino).'%'])
                    ->orWhereRaw('LOWER(email) LIKE ?', ['%'.Str::lower($termino).'%']);
            })
            ->where('estado', true)
            ->limit(8)
            ->get();

        return $usuarios->map(function (User $user): array {
            $nombreCompleto = trim("{$user->name} {$user->apellido_paterno} {$user->apellido_materno}");

            return [
                'id' => $user->id,
                'name' => $user->name,
                'nombre_completo' => $nombreCompleto,
                'ci_nit' => $user->ci_nit ?? 'Sin CI/NIT',
                'telefono' => $user->telefono ?? 'Sin celular',
                'email' => $user->email,
            ];
        })->all();
    }

    /**
     * Reglas de validación en español para el registro express de clientes en POS.
     *
     * @return array<string, array<int, mixed>>
     */
    public function reglasRegistroCliente(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'min:2', 'max:100', "regex:/\A[\pL\pM]+(?:[ '\-][\pL\pM]+)*\z/u"],
            'apellido_paterno' => ['bail', 'required_without:apellido_materno', 'nullable', 'string', 'min:2', 'max:100', "regex:/\A[\pL\pM]+(?:[ '\-][\pL\pM]+)*\z/u"],
            'apellido_materno' => ['bail', 'required_without:apellido_paterno', 'nullable', 'string', 'min:2', 'max:100', "regex:/\A[\pL\pM]+(?:[ '\-][\pL\pM]+)*\z/u"],
            'ci_nit' => ['bail', 'required', 'string', 'min:4', 'max:20', 'regex:/^[0-9A-Za-z\-]+$/'],
            'telefono' => ['bail', 'required', 'string', 'regex:/\A(?:\+591[ -]?)?[67]\d{7}\z/'],
            'email' => ['bail', 'nullable', 'string', 'email:rfc', 'max:254', 'unique:users,email'],
        ];
    }

    /**
     * Mensajes de error en español para el registro de clientes.
     *
     * @return array<string, string>
     */
    public function mensajesRegistroCliente(): array
    {
        return [
            'name.required' => 'El nombre del cliente es obligatorio.',
            'name.min' => 'El nombre debe tener al menos 2 caracteres.',
            'name.regex' => 'El nombre sólo debe contener letras y espacios.',
            'apellido_paterno.required_without' => 'Escriba al menos uno de los dos apellidos (paterno o materno).',
            'apellido_materno.required_without' => 'Escriba al menos uno de los dos apellidos (paterno o materno).',
            'apellido_paterno.regex' => 'El apellido paterno contiene caracteres no válidos.',
            'apellido_materno.regex' => 'El apellido materno contiene caracteres no válidos.',
            'ci_nit.required' => 'El documento de identidad (CI o NIT) es obligatorio.',
            'ci_nit.min' => 'El CI o NIT debe tener al menos 4 caracteres.',
            'ci_nit.regex' => 'El CI o NIT solo puede contener números, letras y guiones.',
            'telefono.required' => 'El teléfono o celular de contacto es obligatorio.',
            'telefono.regex' => 'El celular debe tener 8 dígitos válidos (comenzando con 6 o 7; prefijo +591 opcional).',
            'email.email' => 'El formato del correo electrónico ingresado no es válido.',
            'email.unique' => 'Ya existe un cliente registrado con este correo electrónico.',
        ];
    }

    /**
     * Atributos legibles en español para mensajes de validación.
     *
     * @return array<string, string>
     */
    public function atributosValidacionCliente(): array
    {
        return [
            'name' => 'nombre',
            'apellido_paterno' => 'apellido paterno',
            'apellido_materno' => 'apellido materno',
            'ci_nit' => 'CI / NIT',
            'telefono' => 'teléfono celular',
            'email' => 'correo electrónico',
        ];
    }
}
