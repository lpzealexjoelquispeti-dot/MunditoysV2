<?php

use App\Actions\Modelado3D\ReconstruirModeloJob;
use App\Actions\Modelado3D\SegmentarFotografiasJob;
use App\Livewire\Admin\Modelados3D;
use App\Models\FotografiaModelado;
use App\Models\Modelado3D;
use App\Models\Producto;
use App\Models\ReconstruccionFotogrametria;
use App\Models\User;
use App\Repositories\FotogrametriaRepository;
use App\Services\FotogrametriaService;
use App\Services\ReconstructorFotogrametriaService;
use App\Services\SegmentadorFotogrametriaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function sesionListaParaReconstruir(): Modelado3D
{
    $sesion = Modelado3D::factory()->create(['revision' => 5, 'revision_analizada' => 5, 'status' => 'validado']);
    FotografiaModelado::factory()->compatible()->count(48)
        ->sequence(fn ($secuencia): array => ['ranura' => $secuencia->index + 1])
        ->create(['modelado_3d_id' => $sesion->id]);

    return $sesion->fresh();
}

function motoresFotogrametriaFalsos(): void
{
    Config::set('fotogrametria.motores.colmap', PHP_BINARY);
    Config::set('fotogrametria.motores.openmvs', base_path());
    Config::set('fotogrametria.python', PHP_BINARY);
}

function ejecutarReconstruccion(ReconstruccionFotogrametria $reconstruccion, ?string $token = null): void
{
    (new ReconstruirModeloJob($reconstruccion->id, $token ?? $reconstruccion->token))
        ->handle(app(ReconstructorFotogrametriaService::class), app(FotogrametriaRepository::class));
}

/** @param  list<array<string, mixed>>  $eventos */
function fingirOrquestador(array $eventos, bool $crearGlb = true, int $codigo = 0): void
{
    Process::fake(['*reconstruir_modelo.py*' => function (PendingProcess $proceso) use ($eventos, $crearGlb, $codigo) {
        $entrada = json_decode($proceso->input, true, flags: JSON_THROW_ON_ERROR);
        if ($crearGlb) {
            @mkdir(dirname($entrada['output_path']), 0777, true);
            file_put_contents($entrada['output_path'], str_repeat('g', 1200));
        }

        return Process::result(output: collect($eventos)->map(fn (array $evento): string => json_encode($evento))->implode("\n"), exitCode: $codigo);
    }]);
}

/** @return array<string, mixed> */
function resultadoReconstruccion(): array
{
    return [
        'event' => 'result', 'version' => 'colmap-openmvs-v1', 'glb_bytes' => 1200, 'registered_images' => 48, 'total_images' => 48,
        'unregistered_ids' => [], 'faces' => 150000, 'vertices' => 106000, 'reprojection_error' => 0.74, 'gpu_used' => true,
        'duration_ms' => 248797, 'durations_ms' => ['nube_densa' => 141864], 'texture_size' => 4096,
    ];
}

test('solo reconstruye colecciones validadas por completo', function () {
    administradorAccesos();
    motoresFotogrametriaFalsos();
    Queue::fake();
    $sesion = Modelado3D::factory()->create(['revision' => 1, 'revision_analizada' => 1]);
    FotografiaModelado::factory()->compatible()->count(47)->sequence(fn ($secuencia): array => ['ranura' => $secuencia->index + 1])
        ->create(['modelado_3d_id' => $sesion->id]);

    expect(fn () => app(FotogrametriaService::class)->solicitarReconstruccion($sesion->id))
        ->toThrow(ValidationException::class, 'Primero completa la validación');

    expect(ReconstruccionFotogrametria::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

test('exige los motores instalados antes de encolar la reconstrucción', function () {
    administradorAccesos();
    Queue::fake();
    Config::set('fotogrametria.motores.colmap', base_path('no-existe/colmap.exe'));
    $sesion = sesionListaParaReconstruir();

    expect(fn () => app(FotogrametriaService::class)->solicitarReconstruccion($sesion->id))
        ->toThrow(ValidationException::class, 'no están instalados');

    Queue::assertNothingPushed();
});

test('encola la reconstrucción de la revisión validada con sus parámetros', function () {
    administradorAccesos();
    motoresFotogrametriaFalsos();
    Queue::fake();
    $sesion = sesionListaParaReconstruir();

    $reconstruccion = app(FotogrametriaService::class)->solicitarReconstruccion($sesion->id);

    expect($reconstruccion)->estado->toBe('en_cola')->revision->toBe(5)
        ->parametros->toEqual(config('fotogrametria.reconstruccion.parametros'));
    Queue::assertPushed(ReconstruirModeloJob::class, fn (ReconstruirModeloJob $job): bool => $job->reconstruccionId === $reconstruccion->id
        && $job->token === $reconstruccion->token && $job->connection === 'fotogrametria_reconstruccion');
});

test('bloquea cambios en la colección mientras se reconstruye', function () {
    administradorAccesos();
    $sesion = sesionListaParaReconstruir();
    ReconstruccionFotogrametria::factory()->create(['modelado_3d_id' => $sesion->id, 'revision' => 5, 'estado' => 'procesando']);

    expect(fn () => app(FotogrametriaService::class)->clasificarVista($sesion->id, $sesion->fotografias->first()->id, 'trasera'))
        ->toThrow(ValidationException::class, 'reconstrucción 3D en curso');

    expect($sesion->fotografias->first()->fresh()->vista)->toBe('frente');
});

test('genera el modelo GLB sin publicarlo y registra sus métricas', function () {
    administradorAccesos();
    Storage::fake('public');
    Storage::fake('local');
    motoresFotogrametriaFalsos();
    $sesion = sesionListaParaReconstruir();
    $reconstruccion = ReconstruccionFotogrametria::factory()->create(['modelado_3d_id' => $sesion->id, 'revision' => 5]);
    fingirOrquestador([['event' => 'progress', 'stage' => 'nube_densa', 'progress' => 70], resultadoReconstruccion()]);

    ejecutarReconstruccion($reconstruccion);

    $reconstruccion->refresh();
    expect($reconstruccion)->estado->toBe('completado')->progreso->toBe(100)->duracion_ms->toBe(248797)
        ->and($reconstruccion->resultado['registered_images'])->toBe(48);
    $modelo = $reconstruccion->modeloGenerado;
    expect($modelo)->tipo_generacion->toBe('fotogrametria')->status->toBe('completed')
        ->producto_id->toBeNull()->sesion_origen_id->toBe($sesion->id);
    Storage::disk('public')->assertExists($modelo->modelo_glb_path);
    expect($modelo->modelo_glb_path)->toStartWith('modelos-3d/fotogrametria/sesion-'.$sesion->id.'-r5-');
    Process::assertRan(function (PendingProcess $proceso) use ($sesion): bool {
        $entrada = json_decode($proceso->input, true, flags: JSON_THROW_ON_ERROR);

        return count($entrada['images']) === 48 && $entrada['images'][0]['slot'] === 1
            && $entrada['front_image_id'] === $sesion->fotografias->sortBy('ranura')->first()->id;
    });
});

test('un error del orquestador se muestra sin crear modelos ni archivos', function () {
    administradorAccesos();
    Storage::fake('public');
    Storage::fake('local');
    motoresFotogrametriaFalsos();
    $sesion = sesionListaParaReconstruir();
    $reconstruccion = ReconstruccionFotogrametria::factory()->create(['modelado_3d_id' => $sesion->id, 'revision' => 5]);
    fingirOrquestador([['event' => 'error', 'stage' => 'estructura', 'message' => 'Solo se ubicaron 20 de 48 cámaras.']], crearGlb: false, codigo: 2);

    ejecutarReconstruccion($reconstruccion);

    expect($reconstruccion->fresh())->estado->toBe('error')->mensaje_error->toBe('Solo se ubicaron 20 de 48 cámaras.')->modelo_generado_id->toBeNull();
    expect(Modelado3D::query()->where('tipo_generacion', 'fotogrametria')->count())->toBe(0);
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('descarta el resultado si el GLB no coincide con lo informado', function () {
    administradorAccesos();
    Storage::fake('public');
    Storage::fake('local');
    motoresFotogrametriaFalsos();
    $sesion = sesionListaParaReconstruir();
    $reconstruccion = ReconstruccionFotogrametria::factory()->create(['modelado_3d_id' => $sesion->id, 'revision' => 5]);
    fingirOrquestador([array_replace(resultadoReconstruccion(), ['glb_bytes' => 99])]);

    ejecutarReconstruccion($reconstruccion);

    expect($reconstruccion->fresh())->estado->toBe('error')->mensaje_error->toContain('no se encontró el archivo GLB');
    expect(Storage::disk('public')->allFiles())->toBe([]);
});

test('ignora trabajos con token antiguo o de una revisión ya modificada', function (string $caso) {
    administradorAccesos();
    Storage::fake('public');
    motoresFotogrametriaFalsos();
    $sesion = sesionListaParaReconstruir();
    $reconstruccion = ReconstruccionFotogrametria::factory()->create(['modelado_3d_id' => $sesion->id, 'revision' => 5]);
    if ($caso === 'revision') {
        $sesion->update(['revision' => 6]);
    }
    Process::fake();
    Process::preventStrayProcesses();

    ejecutarReconstruccion($reconstruccion, $caso === 'token' ? 'token-anterior' : null);

    expect($reconstruccion->fresh()->estado)->toBe('en_cola');
    Process::assertNothingRan();
})->with(['token', 'revision']);

test('publicar reemplaza el modelo del juguete sin desvincular sus sesiones de captura', function () {
    administradorAccesos();
    $sesion = sesionListaParaReconstruir();
    $anterior = Modelado3D::factory()->manual()->create(['producto_id' => $sesion->producto_id]);
    $reconstruccion = ReconstruccionFotogrametria::factory()->completada()->create(['modelado_3d_id' => $sesion->id, 'revision' => 5]);

    Livewire::test(Modelados3D::class)
        ->call('abrirSesion', $sesion->id)
        ->assertSee('Publicar en el catálogo')
        ->call('publicarModelo')
        ->assertDispatched('alerta-producto')
        ->assertSee('Publicado en el catálogo de este juguete');

    expect($reconstruccion->modeloGenerado->fresh()->producto_id)->toBe($sesion->producto_id);
    expect($anterior->fresh()->producto_id)->toBeNull();
    expect($sesion->fresh()->producto_id)->toBe($anterior->getOriginal('producto_id'));
    expect(Producto::find($sesion->producto_id)->modelos3D->pluck('id')->all())->toBe([$reconstruccion->modelo_generado_id]);
});

test('el panel muestra la etapa y el progreso de una reconstrucción en curso', function () {
    administradorAccesos();
    $sesion = sesionListaParaReconstruir();
    ReconstruccionFotogrametria::factory()->create([
        'modelado_3d_id' => $sesion->id, 'revision' => 5, 'estado' => 'procesando', 'etapa' => 'nube_densa', 'progreso' => 70,
    ]);

    Livewire::test(Modelados3D::class)
        ->call('abrirSesion', $sesion->id)
        ->assertSee('Generando nube de puntos densa')
        ->assertSee('70 %')
        ->assertDontSee('Generar modelo 3D (GLB)');
});

test('las sesiones de captura no cuentan como modelos 3D del producto', function () {
    $sesion = Modelado3D::factory()->create();
    $modelo = Modelado3D::factory()->manual()->create(['producto_id' => $sesion->producto_id]);

    expect(Producto::find($sesion->producto_id)->modelos3D->pluck('id')->all())->toBe([$modelo->id]);
});

test('subir una fotografía encola el cálculo de su máscara de IA', function () {
    administradorAccesos();
    Storage::fake('local');
    Queue::fake();
    $sesion = Modelado3D::factory()->create();

    app(FotogrametriaService::class)->guardarFoto($sesion->id, UploadedFile::fake()->image('toma.jpg', 800, 600));

    Queue::assertPushed(SegmentarFotografiasJob::class, fn (SegmentarFotografiasJob $job): bool => $job->sesionId === $sesion->id
        && $job->connection === 'fotogrametria_reconstruccion');
});

test('guarda las máscaras de IA solo de las fotos activas que no la tienen', function () {
    Storage::fake('local');
    Config::set('fotogrametria.segmentacion.modelo', base_path('artisan'));
    Config::set('fotogrametria.python', PHP_BINARY);
    $sesion = Modelado3D::factory()->create();
    $pendiente = FotografiaModelado::factory()->create(['modelado_3d_id' => $sesion->id, 'ranura' => 1]);
    $conMascara = FotografiaModelado::factory()->create(['modelado_3d_id' => $sesion->id, 'ranura' => 2, 'mascara' => 'previa.png']);
    FotografiaModelado::factory()->create(['modelado_3d_id' => $sesion->id, 'ranura' => 3, 'reemplazada_en' => now()]);
    Process::fake(['*segmentar_juguete.py*' => function (PendingProcess $proceso) {
        $entrada = json_decode($proceso->input, true, flags: JSON_THROW_ON_ERROR);
        foreach ($entrada['images'] as $imagen) {
            @mkdir(dirname($imagen['output']), 0777, true);
            file_put_contents($imagen['output'], 'png');
        }

        return Process::result(output: collect($entrada['images'])->map(fn (array $imagen): string => json_encode(['event' => 'mask', 'id' => $imagen['id'], 'foreground' => 0.18]))
            ->push(json_encode(['event' => 'result', 'provider' => 'CUDAExecutionProvider']))->implode("\n"));
    }]);

    (new SegmentarFotografiasJob($sesion->id))->handle(app(SegmentadorFotogrametriaService::class));

    expect($pendiente->fresh())->mascara->toBe('fotogrametria/'.$sesion->id.'/mascaras/'.$pendiente->id.'.png')->fraccion_mascara->toBe(0.18);
    expect($conMascara->fresh()->mascara)->toBe('previa.png');
    Process::assertRan(fn (PendingProcess $proceso): bool => count(json_decode($proceso->input, true)['images']) === 1);
});

test('sin el modelo de segmentación instalado no ejecuta nada', function () {
    Config::set('fotogrametria.segmentacion.modelo', base_path('no-existe.onnx'));
    $sesion = Modelado3D::factory()->create();
    FotografiaModelado::factory()->create(['modelado_3d_id' => $sesion->id]);
    Process::fake();
    Process::preventStrayProcesses();

    (new SegmentarFotografiasJob($sesion->id))->handle(app(SegmentadorFotogrametriaService::class));

    Process::assertNothingRan();
});

test('la reconstrucción recibe las máscaras ya calculadas y el modelo de segmentación', function () {
    administradorAccesos();
    Storage::fake('public');
    Storage::fake('local');
    motoresFotogrametriaFalsos();
    $sesion = sesionListaParaReconstruir();
    $sesion->fotografias->first()->update(['mascara' => 'fotogrametria/'.$sesion->id.'/mascaras/1.png']);
    $reconstruccion = ReconstruccionFotogrametria::factory()->create(['modelado_3d_id' => $sesion->id, 'revision' => 5]);
    fingirOrquestador([resultadoReconstruccion()]);

    ejecutarReconstruccion($reconstruccion);

    Process::assertRan(function (PendingProcess $proceso): bool {
        $entrada = json_decode($proceso->input, true, flags: JSON_THROW_ON_ERROR);

        return str_ends_with(str_replace('\\', '/', (string) $entrada['images'][0]['mask']), 'mascaras/1.png')
            && $entrada['images'][1]['mask'] === null
            && $entrada['segmentation_model'] === config('fotogrametria.segmentacion.modelo');
    });
});

test('sirve la máscara privada de una fotografía', function () {
    administradorAccesos();
    Storage::fake('local');
    $foto = FotografiaModelado::factory()->create(['mascara' => 'fotogrametria/1/mascaras/1.png']);
    $imagen = UploadedFile::fake()->image('m.png', 20, 20);
    Storage::disk('local')->put($foto->mascara, file_get_contents($imagen->getRealPath()));

    $this->get(route('admin.fotogrametria.foto', ['modelado' => $foto->modelado_3d_id, 'foto' => $foto->id, 'mascara' => 1]))
        ->assertOk()->assertHeader('Content-Type', 'image/png');
});

test('eliminar la sesión borra fotos, archivos e historial y conserva solo el modelo publicado', function () {
    administradorAccesos();
    Storage::fake('local');
    Storage::fake('public');
    $sesion = sesionListaParaReconstruir();
    Storage::disk('local')->put('fotogrametria/'.$sesion->id.'/imagenes/a.jpg', 'foto');
    Storage::disk('local')->put('fotogrametria/'.$sesion->id.'/mascaras/a.png', 'mascara');
    Storage::disk('local')->put('fotogrametria/otra/imagenes/b.jpg', 'ajena');
    $sinPublicar = ReconstruccionFotogrametria::factory()->completada()->create(['modelado_3d_id' => $sesion->id, 'revision' => 5]);
    $sinPublicar->modeloGenerado->update(['sesion_origen_id' => $sesion->id]);
    Storage::disk('public')->put($sinPublicar->modeloGenerado->modelo_glb_path, 'glb');
    $publicado = Modelado3D::factory()->manual()->create([
        'tipo_generacion' => 'fotogrametria', 'producto_id' => $sesion->producto_id, 'sesion_origen_id' => $sesion->id,
    ]);
    Storage::disk('public')->put($publicado->modelo_glb_path, 'glb');

    Livewire::test(Modelados3D::class)
        ->call('abrirSesion', $sesion->id)
        ->assertSeeHtml("confirmarSesion3D(\$wire, 'eliminarSesion'")
        ->call('eliminarSesion')
        ->assertSet('sesionId', null)
        ->assertDispatched('alerta-producto');

    expect(Modelado3D::withTrashed()->find($sesion->id))->toBeNull();
    expect(FotografiaModelado::query()->where('modelado_3d_id', $sesion->id)->count())->toBe(0);
    expect(ReconstruccionFotogrametria::query()->count())->toBe(0);
    expect(Modelado3D::withTrashed()->find($sinPublicar->modelo_generado_id))->toBeNull();
    Storage::disk('public')->assertMissing($sinPublicar->modeloGenerado->modelo_glb_path);
    expect($publicado->fresh())->producto_id->toBe($sesion->producto_id)->sesion_origen_id->toBeNull();
    Storage::disk('public')->assertExists($publicado->modelo_glb_path);
    expect(Storage::disk('local')->allFiles('fotogrametria/'.$sesion->id))->toBe([]);
    Storage::disk('local')->assertExists('fotogrametria/otra/imagenes/b.jpg');
});

test('no elimina una sesión mientras se reconstruye', function () {
    administradorAccesos();
    $sesion = sesionListaParaReconstruir();
    ReconstruccionFotogrametria::factory()->create(['modelado_3d_id' => $sesion->id, 'revision' => 5, 'estado' => 'procesando']);

    expect(fn () => app(FotogrametriaService::class)->eliminarSesion($sesion->id))
        ->toThrow(ValidationException::class, 'reconstrucción 3D en curso');

    expect(Modelado3D::find($sesion->id))->not->toBeNull();
});

test('un vendedor no puede eliminar sesiones de captura', function () {
    administradorAccesos();
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    $sesion = Modelado3D::factory()->create();
    $this->actingAs($vendedor);

    Livewire::test(Modelados3D::class)
        ->call('abrirSesion', $sesion->id)
        ->assertDontSeeHtml("confirmarSesion3D(\$wire, 'eliminarSesion'")
        ->call('eliminarSesion')
        ->assertForbidden();

    expect(Modelado3D::find($sesion->id))->not->toBeNull();
});

test('mandar una sesión a la papelera conserva sus fotos y se puede restaurar', function () {
    administradorAccesos();
    Storage::fake('local');
    $sesion = sesionListaParaReconstruir();
    Storage::disk('local')->put('fotogrametria/'.$sesion->id.'/imagenes/a.jpg', 'foto');

    $componente = Livewire::test(Modelados3D::class)
        ->call('abrirSesion', $sesion->id)
        ->call('archivarSesion', $sesion->id)
        ->assertSet('sesionId', null)
        ->assertDispatched('alerta-producto');

    expect(Modelado3D::find($sesion->id))->toBeNull()
        ->and(Modelado3D::onlyTrashed()->find($sesion->id))->not->toBeNull()
        ->and(FotografiaModelado::query()->where('modelado_3d_id', $sesion->id)->count())->toBe(48);
    Storage::disk('local')->assertExists('fotogrametria/'.$sesion->id.'/imagenes/a.jpg');
    $componente->assertSeeHtml("confirmarSesion3D(\$wire, 'restaurarSesion', {$sesion->id})");

    $componente->call('restaurarSesion', $sesion->id);

    expect(Modelado3D::find($sesion->id))->not->toBeNull();
    $componente->assertDontSeeHtml("confirmarSesion3D(\$wire, 'restaurarSesion', {$sesion->id})");
});

test('eliminar definitivamente una sesión de la papelera borra sus fotos del disco', function () {
    administradorAccesos();
    Storage::fake('local');
    $sesion = sesionListaParaReconstruir();
    Storage::disk('local')->put('fotogrametria/'.$sesion->id.'/imagenes/a.jpg', 'foto');
    $sesion->delete();

    Livewire::test(Modelados3D::class)
        ->call('eliminarSesion', $sesion->id)
        ->assertDispatched('alerta-producto');

    expect(Modelado3D::withTrashed()->find($sesion->id))->toBeNull()
        ->and(FotografiaModelado::query()->where('modelado_3d_id', $sesion->id)->count())->toBe(0);
    expect(Storage::disk('local')->allFiles('fotogrametria/'.$sesion->id))->toBe([]);
});

test('un vendedor no puede archivar ni restaurar sesiones de captura', function () {
    administradorAccesos();
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    $activa = Modelado3D::factory()->create();
    $archivada = Modelado3D::factory()->create();
    $archivada->delete();
    $this->actingAs($vendedor);

    Livewire::test(Modelados3D::class)->call('archivarSesion', $activa->id)->assertForbidden();
    Livewire::test(Modelados3D::class)->call('restaurarSesion', $archivada->id)->assertForbidden();

    expect(Modelado3D::find($activa->id))->not->toBeNull()
        ->and(Modelado3D::onlyTrashed()->find($archivada->id))->not->toBeNull();
});

test('la sesión guarda el modo de captura y la reconstrucción lo recibe', function () {
    administradorAccesos();
    Storage::fake('public');
    Storage::fake('local');
    motoresFotogrametriaFalsos();
    $sesion = sesionListaParaReconstruir();

    Livewire::test(Modelados3D::class)
        ->call('abrirSesion', $sesion->id)
        ->assertSee('Giré el juguete (cámara fija)')
        ->call('cambiarModoCaptura', 'alrededor');

    expect($sesion->fresh()->modo_captura)->toBe('alrededor');
    $reconstruccion = ReconstruccionFotogrametria::factory()->create(['modelado_3d_id' => $sesion->id, 'revision' => 5]);
    fingirOrquestador([resultadoReconstruccion()]);

    ejecutarReconstruccion($reconstruccion);

    Process::assertRan(fn (PendingProcess $proceso): bool => json_decode($proceso->input, true)['options']['capture_mode'] === 'alrededor');
});

test('crear una sesión registra cómo se tomarán las fotos y rechaza modos desconocidos', function () {
    administradorAccesos();
    $productoId = Modelado3D::factory()->create()->producto_id;
    $servicio = app(FotogrametriaService::class);

    expect($servicio->crearSesion($productoId)->modo_captura)->toBe('plato_giratorio');
    expect($servicio->crearSesion($productoId, 'alrededor')->modo_captura)->toBe('alrededor');
    expect(fn () => $servicio->crearSesion($productoId, 'dron'))->toThrow(ValidationException::class);
});

test('cancelar libera una reconstrucción colgada y permite volver a eliminar la sesión', function () {
    administradorAccesos();
    $sesion = sesionListaParaReconstruir();
    $colgada = ReconstruccionFotogrametria::factory()->create(['modelado_3d_id' => $sesion->id, 'revision' => 5, 'estado' => 'procesando']);

    Livewire::test(Modelados3D::class)
        ->call('abrirSesion', $sesion->id)
        ->assertSee('Cancelar reconstrucción')
        ->call('cancelarReconstruccion')
        ->assertDispatched('alerta-producto');

    expect($colgada->fresh()->estado)->toBe('error');
    app(FotogrametriaService::class)->eliminarSesion($sesion->id);
    expect(Modelado3D::withTrashed()->find($sesion->id))->toBeNull();
});
