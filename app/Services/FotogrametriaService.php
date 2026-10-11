<?php

namespace App\Services;

use App\Actions\Modelado3D\ReconstruirModeloJob;
use App\Actions\Modelado3D\SegmentarFotografiasJob;
use App\Actions\Modelado3D\ValidarFotografiasJob;
use App\Models\FotografiaModelado;
use App\Models\Modelado3D;
use App\Models\Producto;
use App\Models\ReconstruccionFotogrametria;
use App\Repositories\FotogrametriaRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class FotogrametriaService
{
    public function __construct(
        private FotogrametriaRepository $repository,
        private Modelado3DService $modelados,
    ) {}

    public function buscarProductos(string $termino): Collection
    {
        return $this->modelados->buscarProductos($termino);
    }

    public function listarSesiones(): Collection
    {
        Gate::authorize('viewAny', Modelado3D::class);

        return $this->repository->listar();
    }

    public function obtenerSesion(int $id): Modelado3D
    {
        $sesion = $this->repository->obtener($id);
        Gate::authorize('view', $sesion);

        return $sesion;
    }

    public function crearSesion(int $productoId, string $modoCaptura = 'plato_giratorio'): Modelado3D
    {
        Gate::authorize('create', Modelado3D::class);
        $this->validarModoCaptura($modoCaptura);
        $producto = Producto::query()->findOrFail($productoId);

        return Modelado3D::query()->create([
            'producto_id' => $producto->id,
            'task_id' => 'local-'.Str::ulid(),
            'tipo_generacion' => 'fotogrametria_local',
            'modo_captura' => $modoCaptura,
            'status' => 'borrador',
            'revision' => 0,
            'progreso' => 0,
        ]);
    }

    public function guardarFoto(int $sesionId, UploadedFile $foto, ?int $reemplazarId = null): FotografiaModelado
    {
        Gate::authorize('update', $this->repository->obtener($sesionId));
        Validator::make(['foto' => $foto], [
            'foto' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:'.config('fotogrametria.max_kilobytes')],
        ], ['foto.max' => 'Cada fotografía puede pesar hasta 10 MB.', 'foto.image' => 'Selecciona una imagen JPG, PNG o WEBP válida.'])->validate();

        $dimensiones = getimagesize($foto->getRealPath());
        if ($dimensiones === false || config('fotogrametria.max_pixeles') < $dimensiones[0] * $dimensiones[1]) {
            throw ValidationException::withMessages(['foto' => 'La fotografía supera los 20 megapíxeles. Usa una copia de menor resolución.']);
        }

        $rutas = [];
        try {
            $nueva = DB::transaction(function () use ($sesionId, $foto, $reemplazarId, $dimensiones, &$rutas): FotografiaModelado {
                $sesion = $this->sesionEditable($sesionId);
                $anterior = $reemplazarId === null ? null : $sesion->fotografias()->findOrFail($reemplazarId);
                if ($anterior === null && $sesion->fotografias->count() >= config('fotogrametria.objetivo_fotos')) {
                    throw ValidationException::withMessages(['foto' => 'La colección ya tiene 48 fotografías. Usa Reemplazar en una posición existente.']);
                }

                $directorio = 'fotogrametria/'.$sesion->id.'/imagenes';
                $ruta = $foto->store($directorio, 'local');
                if (! is_string($ruta)) {
                    throw new \RuntimeException('No se pudo guardar la fotografía.');
                }
                $rutas[] = $ruta;
                $miniatura = $directorio.'/'.Str::ulid().'-miniatura.jpg';
                $rutas[] = $miniatura;
                $this->guardarMiniatura($foto, $miniatura);

                $ranura = $anterior?->ranura ?? ($sesion->fotografias->max('ranura') ?? 0) + 1;
                $anterior?->update(['reemplazada_en' => now()]);
                $nueva = $sesion->fotografias()->create([
                    'ranura' => $ranura,
                    'version' => ($anterior?->version ?? 0) + 1,
                    'nombre_original' => mb_substr(basename($foto->getClientOriginalName()), 0, 200),
                    'ruta' => $ruta,
                    'miniatura' => $miniatura,
                    'sha256' => hash_file('sha256', $foto->getRealPath()),
                    'ancho' => $dimensiones[0], 'alto' => $dimensiones[1], 'bytes' => $foto->getSize(),
                    'estado' => 'pendiente',
                    'vista' => $anterior?->vista ?? 'sin_clasificar',
                ]);

                if ($sesion->referencia_foto_id === null || ($anterior !== null && $sesion->referencia_foto_id === $anterior->id)) {
                    $sesion->referencia_foto_id = $nueva->id;
                }
                $this->invalidarAnalisis($sesion, conservarDiagnosticos: true);

                return $nueva;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($rutas);
            throw $exception;
        }

        // Fuera de la transacción: el candado de job único usa la tabla cache_locks y, en PostgreSQL,
        // un choque de ese candado abortaría la transacción de la fotografía.
        if (config('fotogrametria.segmentacion.habilitada')) {
            SegmentarFotografiasJob::dispatch($nueva->modelado_3d_id);
        }

        return $nueva;
    }

    public function referencia(int $sesionId, int $fotoId): void
    {
        DB::transaction(function () use ($sesionId, $fotoId): void {
            $sesion = $this->sesionEditable($sesionId);
            $foto = $sesion->fotografias()->findOrFail($fotoId);
            if ($sesion->referencia_foto_id !== $foto->id) {
                $sesion->referencia_foto_id = $foto->id;
                $this->invalidarAnalisis($sesion);
            }
        });
    }

    public function clasificarVista(int $sesionId, int $fotoId, string $vista): void
    {
        Validator::make(['vista' => $vista], ['vista' => ['required', Rule::in(config('fotogrametria.vistas'))]])->validate();
        DB::transaction(function () use ($sesionId, $fotoId, $vista): void {
            $sesion = $this->sesionEditable($sesionId);
            $sesion->fotografias()->findOrFail($fotoId)->update(['vista' => $vista]);
            $this->actualizarEstadoColeccion($sesion->fresh('fotografias'));
        });
    }

    /**
     * Indica cómo se tomaron las fotos. No cambia la validación: solo cómo se ubican las cámaras al reconstruir.
     */
    public function cambiarModoCaptura(int $sesionId, string $modoCaptura): void
    {
        $this->validarModoCaptura($modoCaptura);
        DB::transaction(function () use ($sesionId, $modoCaptura): void {
            $this->sesionEditable($sesionId)->update(['modo_captura' => $modoCaptura]);
        });
    }

    /**
     * Asigna a las fotos sin clasificar la vista que les corresponde según el protocolo de captura.
     */
    public function sugerirVistas(int $sesionId): int
    {
        return DB::transaction(function () use ($sesionId): int {
            $sesion = $this->sesionEditable($sesionId);
            $pendientes = $sesion->fotografias->where('vista', 'sin_clasificar');
            foreach ($pendientes as $foto) {
                $foto->update(['vista' => $this->vistaSegunProtocolo($foto->ranura)]);
            }
            $this->actualizarEstadoColeccion($sesion->fresh('fotografias'));

            return $pendientes->count();
        });
    }

    /**
     * Vuelta 1: rodeo a media altura (frente → derecha → trasera → izquierda); vuelta 2: superior; vuelta 3: inferior.
     */
    public function vistaSegunProtocolo(int $ranura): string
    {
        $porVuelta = (int) config('fotogrametria.protocolo.fotos_por_vuelta');
        $vuelta = config('fotogrametria.protocolo.vueltas')[intdiv($ranura - 1, $porVuelta)] ?? null;
        if ($vuelta === null) {
            return 'sin_clasificar';
        }
        if ($vuelta !== 'media_altura') {
            return $vuelta;
        }

        $sectores = config('fotogrametria.protocolo.sectores_media_altura');
        $grados = (($ranura - 1) % $porVuelta) * 360 / $porVuelta;
        $anchoSector = 360 / count($sectores);

        return $sectores[(int) floor(fmod($grados + $anchoSector / 2, 360) / $anchoSector)];
    }

    /**
     * Tomas compatibles y consecutivas de una misma vuelta sin correspondencias verificadas entre sí.
     *
     * @return list<array{desde: int, hasta: int}>
     */
    public function saltosDeSecuencia(Modelado3D $sesion): array
    {
        $pares = collect($sesion->resultado_validacion['pairs'] ?? [])
            ->filter(fn (array $par): bool => $par['compatible'] ?? false)
            ->map(fn (array $par): string => min($par['a'], $par['b']).'-'.max($par['a'], $par['b']))
            ->flip();
        $porVuelta = (int) config('fotogrametria.protocolo.fotos_por_vuelta');
        $saltos = [];

        foreach ($sesion->fotografias->groupBy(fn (FotografiaModelado $foto): int => intdiv($foto->ranura - 1, $porVuelta)) as $vuelta) {
            $fotos = $vuelta->sortBy('ranura')->values();
            $consecutivas = $fotos->count() === $porVuelta ? $fotos->push($fotos->first()) : $fotos;
            foreach ($consecutivas->sliding(2) as $par) {
                [$desde, $hasta] = $par->values()->all();
                if ($desde->estado !== 'compatible' || $hasta->estado !== 'compatible') {
                    continue;
                }
                if (! $pares->has(min($desde->id, $hasta->id).'-'.max($desde->id, $hasta->id))) {
                    $saltos[] = ['desde' => $desde->ranura, 'hasta' => $hasta->ranura];
                }
            }
        }

        return $saltos;
    }

    public function etiquetar(int $sesionId, int $fotoId, ?bool $apta): void
    {
        DB::transaction(function () use ($sesionId, $fotoId, $apta): void {
            $sesion = $this->sesionEditable($sesionId);
            $sesion->fotografias()->findOrFail($fotoId)->update([
                'etiqueta_humana' => $apta, 'revisado_por' => $apta === null ? null : auth()->id(),
                'revisado_en' => $apta === null ? null : now(),
            ]);
            $this->actualizarEstadoColeccion($sesion->fresh('fotografias'));
        });
    }

    public function solicitarAnalisis(int $sesionId): void
    {
        DB::transaction(function () use ($sesionId): void {
            $sesion = $this->sesionEditable($sesionId);
            if ($sesion->fotografias->count() < 3) {
                throw ValidationException::withMessages(['coleccion' => 'Sube al menos 3 fotografías para comprobar su relación.']);
            }
            if (! $sesion->fotografias->contains('id', $sesion->referencia_foto_id)) {
                throw ValidationException::withMessages(['coleccion' => 'Selecciona como referencia una fotografía clara del juguete.']);
            }
            if (! is_file(config('fotogrametria.python'))) {
                throw ValidationException::withMessages(['coleccion' => 'El analizador local no está configurado. Revisa FOTOGRAMETRIA_PYTHON.']);
            }

            $token = (string) Str::uuid();
            $sesion->update(['status' => 'en_cola', 'progreso' => 0, 'error_message' => null, 'validacion_token' => $token,
                'revision_analizada' => null, 'resultado_validacion' => null, 'analizado_en' => null]);
            $sesion->fotografias()->update(['estado' => 'pendiente', 'motivos' => null, 'metricas' => null]);
            ValidarFotografiasJob::dispatch($sesion->id, $sesion->revision, $token)->afterCommit();
        });
    }

    /**
     * Encola la reconstrucción 3D local de una colección validada.
     */
    public function solicitarReconstruccion(int $sesionId): ReconstruccionFotogrametria
    {
        return DB::transaction(function () use ($sesionId): ReconstruccionFotogrametria {
            $sesion = $this->sesionEditable($sesionId);
            if (! $this->estaLista($sesion)) {
                throw ValidationException::withMessages(['reconstruccion' => 'Primero completa la validación: 48 fotografías compatibles, con vista asignada y sin etiquetas «No apta».']);
            }
            if (! is_file(config('fotogrametria.motores.colmap')) || ! is_dir(config('fotogrametria.motores.openmvs'))) {
                throw ValidationException::withMessages(['reconstruccion' => 'Los motores de reconstrucción (COLMAP y OpenMVS) no están instalados. Revisa FOTOGRAMETRIA_COLMAP y FOTOGRAMETRIA_OPENMVS.']);
            }
            $sesion->reconstrucciones()->whereIn('estado', ['en_cola', 'procesando'])
                ->update(['estado' => 'error', 'mensaje_error' => 'La reconstrucción anterior se interrumpió.', 'finalizado_en' => now()]);

            $reconstruccion = $sesion->reconstrucciones()->create([
                'revision' => $sesion->revision,
                'estado' => 'en_cola',
                'token' => (string) Str::uuid(),
                'parametros' => config('fotogrametria.reconstruccion.parametros'),
            ]);
            ReconstruirModeloJob::dispatch($reconstruccion->id, $reconstruccion->token)->afterCommit();

            return $reconstruccion;
        });
    }

    /**
     * Detiene una reconstrucción colgada (por ejemplo, tras reiniciar el equipo). Si el proceso sigue vivo, su resultado se descarta.
     */
    public function cancelarReconstruccion(int $sesionId): void
    {
        $sesion = $this->obtenerSesion($sesionId);
        Gate::authorize('update', $sesion);
        $sesion->reconstrucciones()->whereIn('estado', ['en_cola', 'procesando'])->update([
            'estado' => 'error', 'mensaje_error' => 'Reconstrucción cancelada. Las fotografías se conservaron; puedes volver a generar el modelo.', 'finalizado_en' => now(),
        ]);
    }

    public function reconstruccionActual(Modelado3D $sesion): ?ReconstruccionFotogrametria
    {
        return $sesion->reconstrucciones()->with('modeloGenerado.producto')->latest('id')->first();
    }

    /**
     * Enlaza el último modelo reconstruido al juguete de la sesión; reemplaza al modelo que tuviera antes.
     */
    public function publicarModelo(int $sesionId): Modelado3D
    {
        $sesion = $this->obtenerSesion($sesionId);
        Gate::authorize('update', $sesion);
        $reconstruccion = $sesion->reconstrucciones()->where('estado', 'completado')->whereNotNull('modelo_generado_id')->latest('id')->first();
        if ($reconstruccion === null || $sesion->producto_id === null) {
            throw ValidationException::withMessages(['reconstruccion' => 'Todavía no hay un modelo reconstruido para publicar en este juguete.']);
        }

        return $this->modelados->enlazar($reconstruccion->modelo_generado_id, $sesion->producto_id);
    }

    /**
     * Borra la sesión con sus fotos, máscaras e historial para volver a capturar desde cero.
     * Los modelos generados sin publicar se eliminan; el publicado en el catálogo se conserva.
     */
    /**
     * Envía la sesión a la papelera (soft delete). Conserva fotos, máscaras e historial para poder restaurarla.
     */
    public function archivarSesion(int $sesionId): void
    {
        DB::transaction(function () use ($sesionId): void {
            $sesion = $this->sesionEditable($sesionId);
            Gate::authorize('delete', $sesion);
            $sesion->delete();
        });
    }

    public function restaurarSesion(int $sesionId): void
    {
        $sesion = $this->repository->obtener($sesionId, incluirArchivadas: true);
        Gate::authorize('restore', $sesion);

        if (! $sesion->trashed()) {
            throw ValidationException::withMessages(['sesion' => 'Esta sesión no está en la papelera.']);
        }

        $sesion->restore();
    }

    /** @return Collection<int, Modelado3D> */
    public function listarSesionesArchivadas(): Collection
    {
        Gate::authorize('viewAny', Modelado3D::class);

        return $this->repository->listarArchivadas();
    }

    /**
     * Borra la sesión de forma definitiva (activa o archivada) junto con sus archivos en disco,
     * para liberar el espacio que ocupan las fotografías y los modelos no publicados.
     */
    public function eliminarSesion(int $sesionId): void
    {
        $glbSinPublicar = DB::transaction(function () use ($sesionId): array {
            $sesion = $this->sesionEditable($sesionId, incluirArchivadas: true);
            Gate::authorize('forceDelete', $sesion);
            $modelos = Modelado3D::query()->where('sesion_origen_id', $sesion->id)->whereNull('producto_id')->get();
            $modelos->each(fn (Modelado3D $modelo): ?bool => $modelo->forceDelete());
            $sesion->forceDelete();

            return $modelos->pluck('modelo_glb_path')->filter()->values()->all();
        });

        Storage::disk('local')->deleteDirectory('fotogrametria/'.$sesionId);
        Storage::disk('public')->delete($glbSinPublicar);
    }

    /** @return array<string, mixed> */
    public function resumen(int $sesionId): array
    {
        $sesion = $this->obtenerSesion($sesionId);
        $fotos = $sesion->fotografias;
        $matriz = ['vp' => 0, 'fn' => 0, 'fp' => 0, 'vn' => 0];
        $vigente = $sesion->revision_analizada !== null && $sesion->revision === $sesion->revision_analizada;
        foreach ($fotos as $foto) {
            if (! $vigente || $foto->etiqueta_humana === null || ! in_array($foto->estado, ['compatible', 'rejected'], true)) {
                continue;
            }
            $invalida = ! $foto->etiqueta_humana;
            $rechazada = $foto->estado === 'rejected';
            $casilla = $invalida ? ($rechazada ? 'vp' : 'fn') : ($rechazada ? 'fp' : 'vn');
            $matriz[$casilla]++;
        }
        $evaluadas = array_sum($matriz);
        $etiquetadas = $fotos->whereNotNull('etiqueta_humana')->count();
        $dividir = fn (int $a, int $b): ?float => $b > 0 ? $a / $b : null;

        return [
            'total' => $fotos->count(),
            'compatibles' => $fotos->where('estado', 'compatible')->count(),
            'revision' => $fotos->where('estado', 'review')->count(),
            'rechazadas' => $fotos->where('estado', 'rejected')->count(),
            'pendientes' => $fotos->where('estado', 'pendiente')->count(),
            'listo' => $this->estaLista($sesion),
            'etiquetadas' => $etiquetadas,
            'evaluadas' => $evaluadas,
            'matriz' => $matriz,
            'metricas' => [
                'precision' => $dividir($matriz['vp'], $matriz['vp'] + $matriz['fp']),
                'recall' => $dividir($matriz['vp'], $matriz['vp'] + $matriz['fn']),
                'f1' => $dividir(2 * $matriz['vp'], 2 * $matriz['vp'] + $matriz['fp'] + $matriz['fn']),
                'exactitud' => $dividir($matriz['vp'] + $matriz['vn'], $evaluadas),
                'cobertura' => $dividir($evaluadas, $etiquetadas),
            ],
            'pares' => $vigente ? ($sesion->resultado_validacion['pairs'] ?? []) : [],
            'orden_sugerido' => $vigente ? ($sesion->resultado_validacion['suggested_order'] ?? []) : [],
            'saltos' => $vigente ? $this->saltosDeSecuencia($sesion) : [],
            'vistas' => $fotos->countBy('vista')->all(),
            'mascaras' => $fotos->whereNotNull('mascara')->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function exportar(int $sesionId): array
    {
        $sesion = $this->obtenerSesion($sesionId);

        return [
            'protocolo' => [
                'metodologia' => 'CRISP-DM', 'fase_actual' => 'Preparación y evaluación de una línea base heurística',
                'objetivo' => 'Detectar fotografías no utilizables antes de la reconstrucción local.',
                'clase_positiva' => 'fotografia_invalida',
                'etiquetado' => 'Humano e independiente de las predicciones; revisión es abstención.',
                'particion_futura' => 'Separar entrenamiento, validación y prueba por producto. Mantener juntas sus sesiones y derivados.',
                'limitacion' => 'Estos resultados describen esta colección, no estiman rendimiento sobre juguetes nuevos. No se ha entrenado un modelo ML.',
                'orientacion' => 'Etiquetas manuales. El orden sugerido describe conectividad, no ángulos de cámara.',
            ],
            'sesion' => ['id' => $sesion->id, 'producto_id' => $sesion->producto_id, 'revision' => $sesion->revision,
                'revision_analizada' => $sesion->revision_analizada, 'estado' => $sesion->status, 'referencia_foto_id' => $sesion->referencia_foto_id],
            'resumen' => $this->resumen($sesionId),
            'fotografias' => $sesion->fotografias->map(fn (FotografiaModelado $foto): array => $foto->only([
                'id', 'ranura', 'version', 'nombre_original', 'sha256', 'ancho', 'alto', 'bytes', 'estado',
                'vista', 'motivos', 'metricas', 'etiqueta_humana', 'revisado_por', 'revisado_en',
            ]))->all(),
            'ejecuciones' => $sesion->validaciones()->orderBy('id')->get()->map(fn ($ejecucion): array => $ejecucion->only([
                'id', 'revision', 'version_algoritmo', 'parametros', 'resultado', 'duracion_ms', 'created_at',
            ]))->all(),
            'reconstrucciones' => $sesion->reconstrucciones()->orderBy('id')->get()->map(fn (ReconstruccionFotogrametria $reconstruccion): array => $reconstruccion->only([
                'id', 'revision', 'estado', 'etapa', 'parametros', 'resultado', 'mensaje_error', 'duracion_ms', 'iniciado_en', 'finalizado_en',
            ]))->all(),
        ];
    }

    /**
     * Lista para reconstruir: todas compatibles, o «Revisar» (abstención del validador) confirmadas como aptas por una persona.
     */
    public function estaLista(Modelado3D $sesion): bool
    {
        return $sesion->revision_analizada !== null && $sesion->revision === $sesion->revision_analizada
            && $sesion->fotografias->count() === (int) config('fotogrametria.objetivo_fotos')
            && $sesion->fotografias->every(fn (FotografiaModelado $foto): bool => $foto->vista !== 'sin_clasificar'
                && $foto->etiqueta_humana !== false
                && ($foto->estado === 'compatible' || ($foto->estado === 'review' && $foto->etiqueta_humana === true)));
    }

    /**
     * La persona confirma como aptas las fotos que el validador dejó en «Revisar» y aún no tienen evaluación humana.
     */
    public function confirmarRevisadas(int $sesionId): int
    {
        return DB::transaction(function () use ($sesionId): int {
            $sesion = $this->sesionEditable($sesionId);
            if ($sesion->revision_analizada === null || $sesion->revision !== $sesion->revision_analizada) {
                throw ValidationException::withMessages(['coleccion' => 'Vuelve a validar la colección antes de confirmar sus fotografías.']);
            }
            $confirmadas = $sesion->fotografias()->where('estado', 'review')->whereNull('etiqueta_humana')
                ->update(['etiqueta_humana' => true, 'revisado_por' => auth()->id(), 'revisado_en' => now()]);
            $this->actualizarEstadoColeccion($sesion->fresh('fotografias'));

            return $confirmadas;
        });
    }

    public function actualizarEstadoColeccion(Modelado3D $sesion): void
    {
        if ($sesion->revision_analizada !== null && $sesion->revision === $sesion->revision_analizada) {
            $sesion->update(['status' => $this->estaLista($sesion) ? 'validado' : 'revision']);
        }
    }

    private function validarModoCaptura(string $modoCaptura): void
    {
        Validator::make(['modo_captura' => $modoCaptura], [
            'modo_captura' => ['required', Rule::in(array_keys(config('fotogrametria.modos_captura')))],
        ], ['modo_captura.in' => 'Selecciona cómo tomaste las fotografías.'])->validate();
    }

    private function sesionEditable(int $id, bool $incluirArchivadas = false): Modelado3D
    {
        $sesion = $this->repository->obtener($id, true, $incluirArchivadas);
        Gate::authorize('update', $sesion);
        if (in_array($sesion->status, ['en_cola', 'analizando'], true)) {
            if ($sesion->updated_at->gt(now()->subSeconds(config('fotogrametria.timeout') + 90))) {
                throw ValidationException::withMessages(['coleccion' => 'La validación está en curso. Espera a que termine antes de cambiar la colección.']);
            }
            $sesion->update(['status' => 'error', 'validacion_token' => null]);
        }
        if ($sesion->reconstrucciones()->whereIn('estado', ['en_cola', 'procesando'])->get()->contains(fn (ReconstruccionFotogrametria $reconstruccion): bool => $reconstruccion->esta_activa)) {
            throw ValidationException::withMessages(['coleccion' => 'Hay una reconstrucción 3D en curso. Espera a que termine antes de cambiar la colección.']);
        }

        return $sesion;
    }

    /**
     * Obliga a revalidar la colección. Al subir o reemplazar fotos se conserva el diagnóstico anterior
     * de las demás para que el panel de corrección siga mostrando qué falta reemplazar.
     */
    private function invalidarAnalisis(Modelado3D $sesion, bool $conservarDiagnosticos = false): void
    {
        $sesion->fill(['revision' => $sesion->revision + 1, 'status' => 'borrador', 'progreso' => 0,
            'error_message' => null, 'validacion_token' => null]);
        if (! $conservarDiagnosticos) {
            $sesion->fill(['revision_analizada' => null, 'resultado_validacion' => null]);
            $sesion->fotografias()->update(['estado' => 'pendiente', 'motivos' => null, 'metricas' => null]);
        }
        $sesion->save();
    }

    private function guardarMiniatura(UploadedFile $foto, string $ruta): void
    {
        $imagen = @imagecreatefromstring(file_get_contents($foto->getRealPath()));
        if ($imagen === false) {
            throw ValidationException::withMessages(['foto' => 'No se pudo leer el contenido de la fotografía.']);
        }
        try {
            if ($foto->getMimeType() === 'image/jpeg' && function_exists('exif_read_data')) {
                $exif = @exif_read_data($foto->getRealPath());
                $orientacion = (int) ($exif['Orientation'] ?? 1);
                if (in_array($orientacion, [2, 4, 5, 7], true)) {
                    imageflip($imagen, IMG_FLIP_HORIZONTAL);
                }
                $angulo = match ($orientacion) {
                    3, 4 => 180, 5, 6 => -90, 7, 8 => 90, default => 0
                };
                if ($angulo !== 0) {
                    $rotada = imagerotate($imagen, $angulo, 0);
                    imagedestroy($imagen);
                    $imagen = $rotada;
                }
            }
            $escala = min(1, 480 / max(imagesx($imagen), imagesy($imagen)));
            $miniatura = imagescale($imagen, max(1, (int) (imagesx($imagen) * $escala)), max(1, (int) (imagesy($imagen) * $escala)));
            ob_start();
            imagejpeg($miniatura, null, 82);
            $contenido = ob_get_clean();
            imagedestroy($miniatura);
            if (! Storage::disk('local')->put($ruta, $contenido)) {
                throw new \RuntimeException('No se pudo guardar la miniatura.');
            }
        } finally {
            imagedestroy($imagen);
        }
    }
}
