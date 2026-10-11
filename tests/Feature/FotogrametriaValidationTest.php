<?php

use App\Actions\Modelado3D\ValidarFotografiasJob;
use App\Livewire\Admin\Modelados3D;
use App\Models\FotografiaModelado;
use App\Models\Modelado3D;
use App\Models\User;
use App\Models\ValidacionFotogrametria;
use App\Services\AnalizadorFotogrametriaService;
use App\Services\FotogrametriaService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

/** @return array<string, mixed> */
function resultadoAnalizadorFotogrametria(Modelado3D $sesion): array
{
    return [
        'version' => 'heuristica-test-1',
        'reference_id' => $sesion->referencia_foto_id,
        'images' => $sesion->fotografias->map(fn (FotografiaModelado $foto): array => [
            'id' => $foto->id, 'status' => 'compatible', 'reasons' => ['Correspondencias verificadas.'],
            'metrics' => ['sharpness' => 180, 'features' => 500, 'connections' => 2],
        ])->all(),
        'pairs' => [],
        'suggested_order' => $sesion->fotografias->pluck('id')->all(),
        'duration_ms' => 250,
        'parameters' => ['max_dimension' => 1024],
        'limitations' => ['No demuestra identidad.'],
    ];
}

test('las fotografías privadas requieren autenticación y acceso al panel', function (bool $original) {
    administradorAccesos();
    $foto = FotografiaModelado::factory()->create();
    $url = route('admin.fotogrametria.foto', ['modelado' => $foto->modelado_3d_id, 'foto' => $foto->id, 'original' => $original]);

    auth()->logout();
    $this->get($url)->assertRedirect(route('login'));

    $cliente = User::factory()->create();
    $cliente->assignRole('Cliente');
    $this->actingAs($cliente)->get($url)->assertForbidden();
})->with(['miniatura' => false, 'original' => true]);

test('sirve la fotografía privada solicitada con su tipo de imagen real', function (bool $original, string $tipo) {
    administradorAccesos();
    Storage::fake('local');
    $foto = FotografiaModelado::factory()->create();
    $miniatura = UploadedFile::fake()->image('miniatura.jpg');
    $archivoOriginal = UploadedFile::fake()->image('original.png');
    Storage::disk('local')->put($foto->miniatura, file_get_contents($miniatura->getRealPath()));
    Storage::disk('local')->put($foto->ruta, file_get_contents($archivoOriginal->getRealPath()));

    $this->get(route('admin.fotogrametria.foto', ['modelado' => $foto->modelado_3d_id, 'foto' => $foto->id, 'original' => $original]))
        ->assertOk()
        ->assertHeader('Content-Type', $tipo)
        ->assertHeader('X-Content-Type-Options', 'nosniff');
})->with(['miniatura JPEG' => [false, 'image/jpeg'], 'original PNG' => [true, 'image/png']]);

test('no permite consultar una foto desde otra sesión ni una versión reemplazada', function (bool $original) {
    administradorAccesos();
    $sesion = Modelado3D::factory()->create();
    $fotoAjena = FotografiaModelado::factory()->create();
    $fotoReemplazada = FotografiaModelado::factory()->create([
        'modelado_3d_id' => $sesion->id, 'reemplazada_en' => now(),
    ]);

    $this->get(route('admin.fotogrametria.foto', ['modelado' => $sesion->id, 'foto' => $fotoAjena->id, 'original' => $original]))->assertNotFound();
    $this->get(route('admin.fotogrametria.foto', ['modelado' => $sesion->id, 'foto' => $fotoReemplazada->id, 'original' => $original]))->assertNotFound();
})->with(['miniatura' => false, 'original' => true]);

test('no expone archivos originales inexistentes o con contenido que no sea imagen', function (?string $contenido) {
    administradorAccesos();
    Storage::fake('local');
    $foto = FotografiaModelado::factory()->create();
    if ($contenido !== null) {
        Storage::disk('local')->put($foto->ruta, $contenido);
    }

    $this->get(route('admin.fotogrametria.foto', ['modelado' => $foto->modelado_3d_id, 'foto' => $foto->id, 'original' => 1]))
        ->assertNotFound();
})->with(['archivo inexistente' => null, 'contenido HTML' => '<html><script>alert(1)</script></html>']);

test('un vendedor no puede modificar una colección aunque pueda verla', function () {
    administradorAccesos();
    $vendedor = User::factory()->create();
    $vendedor->assignRole('Vendedor');
    $foto = FotografiaModelado::factory()->create();
    $this->actingAs($vendedor);

    Livewire::test(Modelados3D::class)
        ->call('abrirSesion', $foto->modelado_3d_id)
        ->call('etiquetarFoto', $foto->id, 'apta')
        ->assertForbidden();

    expect($foto->fresh()->etiqueta_humana)->toBeNull();
});

test('rechaza imágenes inválidas o demasiado grandes sin guardar archivos', function (string $caso) {
    administradorAccesos();
    Storage::fake('local');
    $sesion = Modelado3D::factory()->create();
    $foto = match ($caso) {
        'contenido' => UploadedFile::fake()->create('foto.jpg', 10, 'text/plain'),
        'peso' => UploadedFile::fake()->image('pesada.jpg')->size(10241),
        'pixeles' => UploadedFile::fake()->image('grande.jpg', 11, 10),
    };
    if ($caso === 'pixeles') {
        Config::set('fotogrametria.max_pixeles', 100);
    }

    expect(fn () => app(FotogrametriaService::class)->guardarFoto($sesion->id, $foto))
        ->toThrow(ValidationException::class);

    $this->assertDatabaseCount('fotografias_modelado', 0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with(['contenido no imagen' => 'contenido', 'más de diez MB' => 'peso', 'límite de píxeles' => 'pixeles']);

test('acepta la fotografía 48 y rechaza una posición 49 sin alterar la colección', function () {
    administradorAccesos();
    Storage::fake('local');
    $sesion = Modelado3D::factory()->create();
    FotografiaModelado::factory()->count(47)->sequence(fn ($secuencia): array => ['ranura' => $secuencia->index + 1])
        ->create(['modelado_3d_id' => $sesion->id]);
    $service = app(FotogrametriaService::class);

    $ultima = $service->guardarFoto($sesion->id, UploadedFile::fake()->image('foto-48.jpg'));
    expect($ultima->ranura)->toBe(48);
    expect(fn () => $service->guardarFoto($sesion->id, UploadedFile::fake()->image('foto-49.jpg')))
        ->toThrow(ValidationException::class, 'La colección ya tiene 48 fotografías.');

    expect($sesion->fotografias()->count())->toBe(48);
    expect($sesion->fresh()->revision)->toBe(1);
    Storage::disk('local')->assertExists([$ultima->ruta, $ultima->miniatura]);
});

test('reemplazar conserva la posición y archiva la versión sin copiar la etiqueta humana', function () {
    $admin = administradorAccesos();
    Storage::fake('local');
    $sesion = Modelado3D::factory()->create(['revision' => 4, 'revision_analizada' => 4, 'status' => 'revision']);
    $anterior = FotografiaModelado::factory()->create([
        'modelado_3d_id' => $sesion->id, 'ranura' => 5, 'version' => 2,
        'vista' => 'superior', 'estado' => 'rejected', 'etiqueta_humana' => false,
        'revisado_por' => $admin->id, 'revisado_en' => now(),
    ]);
    $sesion->update(['referencia_foto_id' => $anterior->id]);

    $nueva = app(FotogrametriaService::class)->guardarFoto($sesion->id, UploadedFile::fake()->image('reemplazo.jpg'), $anterior->id);

    expect($nueva->id)->not->toBe($anterior->id);
    expect($nueva)->ranura->toBe(5)->version->toBe(3)->vista->toBe('superior')
        ->estado->toBe('pendiente')->etiqueta_humana->toBeNull()->revisado_por->toBeNull()->revisado_en->toBeNull();
    expect($anterior->fresh()->reemplazada_en)->not->toBeNull();
    expect($sesion->fresh())->referencia_foto_id->toBe($nueva->id)->revision->toBe(5)->revision_analizada->toBe(4)->status->toBe('borrador');
    expect(app(FotogrametriaService::class)->resumen($sesion->id)['listo'])->toBeFalse();
    expect($sesion->fotografias()->pluck('id')->all())->toBe([$nueva->id]);
});

test('no mezcla fotografías de otras sesiones al modificar sus metadatos', function (string $operacion) {
    administradorAccesos();
    $sesion = Modelado3D::factory()->create();
    $fotoAjena = FotografiaModelado::factory()->create();
    $service = app(FotogrametriaService::class);

    expect(fn () => match ($operacion) {
        'referencia' => $service->referencia($sesion->id, $fotoAjena->id),
        'vista' => $service->clasificarVista($sesion->id, $fotoAjena->id, 'frente'),
        'etiqueta' => $service->etiquetar($sesion->id, $fotoAjena->id, false),
    })->toThrow(ModelNotFoundException::class);

    expect($fotoAjena->fresh())->vista->toBe('sin_clasificar')->etiqueta_humana->toBeNull();
    expect($sesion->fresh())->revision->toBe(0)->referencia_foto_id->toBeNull();
})->with(['referencia', 'vista', 'etiqueta']);

test('cambiar la referencia invalida predicciones y conserva las etiquetas humanas', function () {
    administradorAccesos();
    $sesion = Modelado3D::factory()->create([
        'revision' => 3, 'revision_analizada' => 3, 'status' => 'revision', 'progreso' => 100,
        'resultado_validacion' => ['suggested_order' => [1, 2]], 'validacion_token' => 'anterior',
    ]);
    $fotos = FotografiaModelado::factory()->count(2)->sequence(fn ($secuencia): array => ['ranura' => $secuencia->index + 1])->create([
        'modelado_3d_id' => $sesion->id, 'estado' => 'compatible', 'etiqueta_humana' => true,
        'metricas' => ['sharpness' => 200], 'motivos' => ['Conectada.'],
    ]);
    $sesion->update(['referencia_foto_id' => $fotos->first()->id]);

    app(FotogrametriaService::class)->referencia($sesion->id, $fotos->last()->id);

    expect($sesion->fresh())->revision->toBe(4)->revision_analizada->toBeNull()->progreso->toBe(0)
        ->status->toBe('borrador')->resultado_validacion->toBeNull()->validacion_token->toBeNull();
    foreach ($fotos as $foto) {
        expect($foto->fresh())->estado->toBe('pendiente')->metricas->toBeNull()->motivos->toBeNull()->etiqueta_humana->toBeTrue();
    }
});

test('exige tres fotos y una referencia antes de encolar el análisis', function (int $cantidad, string $mensaje) {
    administradorAccesos();
    Queue::fake();
    $sesion = Modelado3D::factory()->create();
    FotografiaModelado::factory()->count($cantidad)->sequence(fn ($secuencia): array => ['ranura' => $secuencia->index + 1])
        ->create(['modelado_3d_id' => $sesion->id]);

    expect(fn () => app(FotogrametriaService::class)->solicitarAnalisis($sesion->id))
        ->toThrow(ValidationException::class, $mensaje);

    expect($sesion->fresh()->status)->toBe('borrador');
    Queue::assertNothingPushed();
})->with([
    'faltan fotografías' => [2, 'Sube al menos 3 fotografías'],
    'falta referencia' => [3, 'Selecciona como referencia'],
]);

test('una colección en análisis no admite cambios que alteren su revisión', function () {
    administradorAccesos();
    $sesion = Modelado3D::factory()->create(['status' => 'analizando', 'revision' => 3, 'validacion_token' => 'activo']);
    $foto = FotografiaModelado::factory()->create(['modelado_3d_id' => $sesion->id]);

    expect(fn () => app(FotogrametriaService::class)->referencia($sesion->id, $foto->id))
        ->toThrow(ValidationException::class, 'La validación está en curso.');

    expect($sesion->fresh())->revision->toBe(3)->validacion_token->toBe('activo')->referencia_foto_id->toBeNull();
});

test('permite reintentar una validación detenida sin reutilizar el token anterior', function (string $estado) {
    administradorAccesos();
    $this->freezeTime();
    Queue::fake([ValidarFotografiasJob::class]);
    Config::set('fotogrametria.python', PHP_BINARY);
    $sesion = Modelado3D::factory()->create(['status' => $estado, 'revision' => 3, 'validacion_token' => 'detenido']);
    $fotos = FotografiaModelado::factory()->count(3)->sequence(fn ($secuencia): array => ['ranura' => $secuencia->index + 1])
        ->create(['modelado_3d_id' => $sesion->id]);
    $sesion->update(['referencia_foto_id' => $fotos->first()->id]);
    $this->travel(config('fotogrametria.timeout') + 91)->seconds();

    app(FotogrametriaService::class)->solicitarAnalisis($sesion->id);

    expect($sesion->fresh())->status->toBe('en_cola')->revision->toBe(3)->validacion_token->not->toBe('detenido');
    Queue::assertPushed(ValidarFotografiasJob::class, fn (ValidarFotografiasJob $job): bool => $job->modeladoId === $sesion->id
        && $job->revision === 3 && $job->token === $sesion->fresh()->validacion_token);
})->with(['en cola detenida' => 'en_cola', 'análisis detenido' => 'analizando']);

test('un reanálisis fallido no conserva la colección lista ni pierde su historial', function () {
    administradorAccesos();
    Queue::fake([ValidarFotografiasJob::class]);
    Config::set('fotogrametria.python', PHP_BINARY);
    $sesion = Modelado3D::factory()->create(['revision' => 48, 'revision_analizada' => 48, 'status' => 'validado', 'analizado_en' => now()]);
    $fotos = FotografiaModelado::factory()->compatible()->count(48)->sequence(fn ($secuencia): array => ['ranura' => $secuencia->index + 1])
        ->create(['modelado_3d_id' => $sesion->id, 'etiqueta_humana' => true, 'metricas' => ['sharpness' => 200], 'motivos' => ['Compatible.']]);
    $sesion->update(['referencia_foto_id' => $fotos->first()->id]);
    $resultadoAnterior = resultadoAnalizadorFotogrametria($sesion->fresh('fotografias'));
    $sesion->update(['resultado_validacion' => $resultadoAnterior]);
    $historial = ValidacionFotogrametria::factory()->create(['modelado_3d_id' => $sesion->id, 'revision' => 48, 'resultado' => $resultadoAnterior]);
    $service = app(FotogrametriaService::class);
    expect($service->resumen($sesion->id)['listo'])->toBeTrue();

    $service->solicitarAnalisis($sesion->id);

    expect($sesion->fresh())->revision->toBe(48)->revision_analizada->toBeNull()->resultado_validacion->toBeNull()->analizado_en->toBeNull();
    expect($sesion->fotografias()->where('estado', 'pendiente')->whereNull('metricas')->whereNull('motivos')->count())->toBe(48);
    expect($sesion->fotografias()->where('etiqueta_humana', true)->count())->toBe(48);
    expect($service->resumen($sesion->id)['listo'])->toBeFalse();
    Queue::assertPushed(ValidarFotografiasJob::class, fn (ValidarFotografiasJob $job): bool => $job->modeladoId === $sesion->id && $job->revision === 48);

    (new ValidarFotografiasJob($sesion->id, 48, $sesion->fresh()->validacion_token))->failed(new RuntimeException('Proceso detenido'));

    expect($sesion->fresh()->status)->toBe('error');
    expect($service->resumen($sesion->id)['listo'])->toBeFalse();
    expect($service->resumen($sesion->id)['evaluadas'])->toBe(0);
    expect($historial->fresh()->resultado)->toBe($resultadoAnterior);
    $this->assertDatabaseCount('validaciones_fotogrametria', 1);
});

test('calcula la matriz con fotografía inválida positiva y excluye las abstenciones', function () {
    administradorAccesos();
    $sesion = Modelado3D::factory()->create(['revision' => 7, 'revision_analizada' => 7]);
    FotografiaModelado::factory()->count(7)->sequence(fn ($secuencia): array => ['ranura' => $secuencia->index + 1])->sequence(
        ['estado' => 'rejected', 'etiqueta_humana' => false],
        ['estado' => 'rejected', 'etiqueta_humana' => false],
        ['estado' => 'compatible', 'etiqueta_humana' => false],
        ['estado' => 'rejected', 'etiqueta_humana' => true],
        ['estado' => 'compatible', 'etiqueta_humana' => true],
        ['estado' => 'review', 'etiqueta_humana' => false],
        ['estado' => 'compatible', 'etiqueta_humana' => null],
    )->create(['modelado_3d_id' => $sesion->id]);

    $resumen = app(FotogrametriaService::class)->resumen($sesion->id);

    expect($resumen['matriz'])->toBe(['vp' => 2, 'fn' => 1, 'fp' => 1, 'vn' => 1]);
    expect($resumen['etiquetadas'])->toBe(6);
    expect($resumen['evaluadas'])->toBe(5);
    expect($resumen['metricas'])->toBe([
        'precision' => 2 / 3, 'recall' => 2 / 3, 'f1' => 2 / 3,
        'exactitud' => 0.6, 'cobertura' => 5 / 6,
    ]);
});

test('las predicciones de una revisión anterior no producen métricas de evaluación', function () {
    administradorAccesos();
    $sesion = Modelado3D::factory()->create(['revision' => 2, 'revision_analizada' => 1]);
    FotografiaModelado::factory()->create(['modelado_3d_id' => $sesion->id, 'estado' => 'rejected', 'etiqueta_humana' => false]);

    $resumen = app(FotogrametriaService::class)->resumen($sesion->id);

    expect($resumen['evaluadas'])->toBe(0);
    expect($resumen['metricas'])->toBe(['precision' => null, 'recall' => null, 'f1' => null, 'exactitud' => null, 'cobertura' => 0.0]);
    expect($resumen['listo'])->toBeFalse();
});

test('exige 48 fotografías compatibles con vista definida y sin rechazo humano', function (int $cantidad, string $vista, ?bool $etiqueta, bool $listo) {
    administradorAccesos();
    $sesion = Modelado3D::factory()->create(['revision' => 48, 'revision_analizada' => 48]);
    FotografiaModelado::factory()->compatible()->count($cantidad)->sequence(fn ($secuencia): array => ['ranura' => $secuencia->index + 1])->create([
        'modelado_3d_id' => $sesion->id, 'estado' => 'compatible', 'vista' => 'frente',
    ]);
    $sesion->fotografias()->first()->update(['vista' => $vista, 'etiqueta_humana' => $etiqueta]);

    $resumen = app(FotogrametriaService::class)->resumen($sesion->id);

    expect($resumen['listo'])->toBe($listo);
})->with([
    'colección completa' => [48, 'superior', true, true],
    'colección incompleta' => [47, 'superior', true, false],
    'falta orientación' => [48, 'sin_clasificar', true, false],
    'rechazo humano' => [48, 'superior', false, false],
]);

test('una etiqueta humana no sobrescribe la predicción ni el progreso del análisis', function () {
    $admin = administradorAccesos();
    $sesion = Modelado3D::factory()->create(['revision' => 2, 'revision_analizada' => 2, 'progreso' => 100]);
    $foto = FotografiaModelado::factory()->create(['modelado_3d_id' => $sesion->id, 'estado' => 'rejected']);

    app(FotogrametriaService::class)->etiquetar($sesion->id, $foto->id, true);

    expect($foto->fresh())->estado->toBe('rejected')->etiqueta_humana->toBeTrue()->revisado_por->toBe($admin->id);
    expect($sesion->fresh())->revision->toBe(2)->revision_analizada->toBe(2)->progreso->toBe(100);
});

test('exporta sólo fotos activas de la colección sin rutas privadas', function () {
    administradorAccesos();
    $sesion = Modelado3D::factory()->create();
    $foto = FotografiaModelado::factory()->create(['modelado_3d_id' => $sesion->id]);
    FotografiaModelado::factory()->create(['modelado_3d_id' => $sesion->id, 'version' => 2, 'reemplazada_en' => now()]);
    FotografiaModelado::factory()->create();

    $this->get(route('admin.fotogrametria.exportar', ['modelado' => $sesion->id]))
        ->assertOk()
        ->assertJsonPath('sesion.id', $sesion->id)
        ->assertJsonCount(1, 'fotografias')
        ->assertJsonPath('fotografias.0.id', $foto->id)
        ->assertJsonMissingPath('fotografias.0.ruta')
        ->assertJsonMissingPath('fotografias.0.miniatura');
});

test('el dataset conserva versiones de su sesión y neutraliza fórmulas en nombres', function () {
    administradorAccesos();
    $sesion = Modelado3D::factory()->create();
    $original = FotografiaModelado::factory()->create([
        'modelado_3d_id' => $sesion->id, 'nombre_original' => '=HYPERLINK("https://example.test")',
        'reemplazada_en' => now(), 'etiqueta_humana' => false,
    ]);
    $actual = FotografiaModelado::factory()->create(['modelado_3d_id' => $sesion->id, 'version' => 2]);
    FotografiaModelado::factory()->create(['nombre_original' => 'sesion-ajena.jpg']);

    $respuesta = $this->get(route('admin.fotogrametria.dataset', ['modelado' => $sesion->id]))->assertOk();
    $filas = array_map(fn (string $linea): array => str_getcsv($linea, escape: ''), explode("\n", trim($respuesta->streamedContent())));

    expect($filas)->toHaveCount(3);
    expect($filas[1][2])->toBe((string) $original->id);
    expect($filas[1][6])->toBe("'".$original->nombre_original);
    expect($filas[1][12])->toBe('0');
    expect($filas[2][2])->toBe((string) $actual->id);
    expect($filas[0])->not->toContain('ruta', 'miniatura');
});

test('el trabajo local ignora tokens o revisiones que ya no corresponden', function (int $revision, string $token, string $estado) {
    administradorAccesos();
    Process::fake();
    $sesion = Modelado3D::factory()->create(['revision' => 5, 'validacion_token' => 'vigente', 'status' => $estado]);

    (new ValidarFotografiasJob($sesion->id, $revision, $token))
        ->handle(app(AnalizadorFotogrametriaService::class), app(FotogrametriaService::class));

    expect($sesion->fresh())->revision->toBe(5)->validacion_token->toBe('vigente')->status->toBe($estado);
    $this->assertDatabaseCount('validaciones_fotogrametria', 0);
    Process::assertNothingRan();
})->with([
    'token anterior' => [5, 'obsoleto', 'en_cola'],
    'revisión anterior' => [4, 'vigente', 'en_cola'],
    'trabajo duplicado en ejecución' => [5, 'vigente', 'analizando'],
]);

test('guarda resultados locales auditables sin modificar las etiquetas humanas', function () {
    administradorAccesos();
    Storage::fake('local');
    Config::set('fotogrametria.python', PHP_BINARY);
    $sesion = Modelado3D::factory()->create(['revision' => 3, 'status' => 'en_cola', 'validacion_token' => 'ejecucion']);
    $fotos = FotografiaModelado::factory()->count(3)->sequence(fn ($secuencia): array => ['ranura' => $secuencia->index + 1])
        ->create(['modelado_3d_id' => $sesion->id, 'etiqueta_humana' => false]);
    $sesion->update(['referencia_foto_id' => $fotos->first()->id]);
    foreach ($fotos as $foto) {
        Storage::disk('local')->put($foto->ruta, 'contenido-local');
    }
    $resultado = resultadoAnalizadorFotogrametria($sesion->fresh('fotografias'));
    Process::fake(['*analizar_imagenes.py*' => Process::result(output: json_encode($resultado, JSON_THROW_ON_ERROR))]);
    Process::preventStrayProcesses();

    (new ValidarFotografiasJob($sesion->id, 3, 'ejecucion'))
        ->handle(app(AnalizadorFotogrametriaService::class), app(FotogrametriaService::class));

    expect($sesion->fresh())->status->toBe('revision')->revision_analizada->toBe(3)->progreso->toBe(100);
    expect($fotos->first()->fresh())->estado->toBe('compatible')->etiqueta_humana->toBeFalse();
    $this->assertDatabaseHas('validaciones_fotogrametria', [
        'modelado_3d_id' => $sesion->id, 'revision' => 3, 'version_algoritmo' => 'heuristica-test-1',
    ]);
    Process::assertRan(function (PendingProcess $process) use ($fotos): bool {
        $entrada = json_decode($process->input, true, flags: JSON_THROW_ON_ERROR);

        return array_column($entrada['images'], 'id') === $fotos->pluck('id')->all()
            && ! array_key_exists('etiqueta_humana', $entrada['images'][0]);
    });
});

test('un fallo del proceso no publica resultados ni marca lista la colección', function () {
    administradorAccesos();
    Storage::fake('local');
    Config::set('fotogrametria.python', PHP_BINARY);
    $sesion = Modelado3D::factory()->create(['revision' => 1, 'status' => 'en_cola', 'validacion_token' => 'fallo']);
    $foto = FotografiaModelado::factory()->create(['modelado_3d_id' => $sesion->id]);
    $sesion->update(['referencia_foto_id' => $foto->id]);
    Storage::disk('local')->put($foto->ruta, 'contenido-local');
    Process::fake(['*analizar_imagenes.py*' => Process::result(errorOutput: 'Falló OpenCV', exitCode: 1)]);
    Process::preventStrayProcesses();

    (new ValidarFotografiasJob($sesion->id, 1, 'fallo'))
        ->handle(app(AnalizadorFotogrametriaService::class), app(FotogrametriaService::class));

    expect($sesion->fresh())->status->toBe('error')->revision_analizada->toBeNull()->resultado_validacion->toBeNull();
    expect($sesion->fresh()->error_message)->not->toBeEmpty();
    expect($foto->fresh()->estado)->toBe('pendiente');
    $this->assertDatabaseCount('validaciones_fotogrametria', 0);
    Process::assertRan(fn (PendingProcess $process): bool => str_contains(implode(' ', (array) $process->command), 'analizar_imagenes.py'));
});

test('descarta un resultado si la colección cambió durante la ejecución local', function () {
    administradorAccesos();
    $sesion = Modelado3D::factory()->create(['revision' => 1, 'status' => 'en_cola', 'validacion_token' => 'anterior']);
    $foto = FotografiaModelado::factory()->create(['modelado_3d_id' => $sesion->id]);
    $sesion->update(['referencia_foto_id' => $foto->id]);
    $resultado = resultadoAnalizadorFotogrametria($sesion->fresh('fotografias'));
    Process::fake(['*analizar_imagenes.py*' => function () use ($sesion, $resultado) {
        $sesion->update(['revision' => 2, 'validacion_token' => 'nueva', 'status' => 'borrador']);

        return Process::result(output: json_encode($resultado, JSON_THROW_ON_ERROR));
    }]);
    Process::preventStrayProcesses();

    (new ValidarFotografiasJob($sesion->id, 1, 'anterior'))
        ->handle(app(AnalizadorFotogrametriaService::class), app(FotogrametriaService::class));

    expect($sesion->fresh())->revision->toBe(2)->status->toBe('borrador')->validacion_token->toBe('nueva')->revision_analizada->toBeNull();
    expect($foto->fresh()->estado)->toBe('pendiente');
    $this->assertDatabaseCount('validaciones_fotogrametria', 0);
    Process::assertRan(fn (PendingProcess $process): bool => str_contains(implode(' ', (array) $process->command), 'analizar_imagenes.py'));
});

test('rechaza diagnósticos de otra sesión antes de guardar cualquier resultado', function () {
    administradorAccesos();
    $sesion = Modelado3D::factory()->create(['revision' => 1, 'status' => 'en_cola', 'validacion_token' => 'mezcla']);
    $foto = FotografiaModelado::factory()->create(['modelado_3d_id' => $sesion->id]);
    $otraFoto = FotografiaModelado::factory()->create();
    $sesion->update(['referencia_foto_id' => $foto->id]);
    $resultado = resultadoAnalizadorFotogrametria($sesion->fresh('fotografias'));
    $resultado['images'][0]['id'] = $otraFoto->id;
    Process::fake(['*analizar_imagenes.py*' => Process::result(output: json_encode($resultado, JSON_THROW_ON_ERROR))]);
    Process::preventStrayProcesses();

    (new ValidarFotografiasJob($sesion->id, 1, 'mezcla'))
        ->handle(app(AnalizadorFotogrametriaService::class), app(FotogrametriaService::class));

    expect($sesion->fresh())->status->toBe('error')->revision_analizada->toBeNull();
    expect($foto->fresh()->estado)->toBe('pendiente');
    expect($otraFoto->fresh()->estado)->toBe('pendiente');
    $this->assertDatabaseCount('validaciones_fotogrametria', 0);
    Process::assertRan(fn (PendingProcess $process): bool => str_contains(implode(' ', (array) $process->command), 'analizar_imagenes.py'));
});

test('asigna la vista según la posición del protocolo 16 · 16 · 16', function (int $ranura, string $vista) {
    expect(app(FotogrametriaService::class)->vistaSegunProtocolo($ranura))->toBe($vista);
})->with([
    'inicio de la vuelta media' => [1, 'frente'],
    'antes del cuarto de vuelta' => [2, 'frente'],
    'cuarto de vuelta' => [4, 'derecha'],
    'media vuelta' => [9, 'trasera'],
    'tres cuartos de vuelta' => [13, 'izquierda'],
    'cierre de la vuelta' => [16, 'frente'],
    'vuelta superior' => [17, 'superior'],
    'vuelta inferior' => [48, 'inferior'],
    'fuera del protocolo' => [49, 'sin_clasificar'],
]);

test('sugerir vistas solo completa las fotos sin clasificar y conserva la revisión', function () {
    administradorAccesos();
    $sesion = Modelado3D::factory()->create(['revision' => 3, 'revision_analizada' => 3]);
    $manual = FotografiaModelado::factory()->create(['modelado_3d_id' => $sesion->id, 'ranura' => 1, 'vista' => 'trasera']);
    $superior = FotografiaModelado::factory()->create(['modelado_3d_id' => $sesion->id, 'ranura' => 20]);
    $ajena = FotografiaModelado::factory()->create(['ranura' => 20]);

    Livewire::test(Modelados3D::class)
        ->call('abrirSesion', $sesion->id)
        ->call('sugerirVistas')
        ->assertDispatched('alerta-producto');

    expect($manual->fresh()->vista)->toBe('trasera');
    expect($superior->fresh()->vista)->toBe('superior');
    expect($ajena->fresh()->vista)->toBe('sin_clasificar');
    expect($sesion->fresh())->revision->toBe(3)->revision_analizada->toBe(3);
});

test('señala tomas consecutivas de una vuelta sin correspondencias verificadas', function () {
    administradorAccesos();
    $sesion = Modelado3D::factory()->create(['revision' => 1, 'revision_analizada' => 1]);
    $fotos = FotografiaModelado::factory()->count(4)->sequence(fn ($secuencia): array => ['ranura' => $secuencia->index + 1])
        ->create(['modelado_3d_id' => $sesion->id, 'estado' => 'compatible']);
    $fotos[3]->update(['estado' => 'rejected']);
    $sesion->update(['resultado_validacion' => ['pairs' => [
        ['a' => $fotos[0]->id, 'b' => $fotos[1]->id, 'compatible' => true],
        ['a' => $fotos[1]->id, 'b' => $fotos[2]->id, 'compatible' => false],
    ]]]);

    expect(app(FotogrametriaService::class)->resumen($sesion->id)['saltos'])->toBe([['desde' => 2, 'hasta' => 3]]);

    $sesion->update(['revision' => 2]);
    expect(app(FotogrametriaService::class)->resumen($sesion->id)['saltos'])->toBe([]);
});

test('el panel de corrección muestra solo las fotos rechazadas o por revisar del análisis vigente', function () {
    administradorAccesos();
    Storage::fake('local');
    $sesion = Modelado3D::factory()->create(['revision' => 1, 'revision_analizada' => 1]);
    FotografiaModelado::factory()->create(['modelado_3d_id' => $sesion->id, 'ranura' => 1, 'estado' => 'rejected', 'motivos' => ['Otro objeto en la toma.']]);
    FotografiaModelado::factory()->create(['modelado_3d_id' => $sesion->id, 'ranura' => 2, 'estado' => 'compatible']);

    Livewire::test(Modelados3D::class)
        ->call('abrirSesion', $sesion->id)
        ->assertSee('Fotografías que necesitan otra toma')
        ->assertSee('Otro objeto en la toma.')
        ->assertViewHas('fotosPorCorregir', fn ($fotos): bool => $fotos->pluck('ranura')->all() === [1]);
});

test('valida de extremo a extremo con el analizador local real y detecta el juguete ajeno', function () {
    if (! is_file(config('fotogrametria.python'))) {
        $this->markTestSkipped('El entorno Python local de fotogrametría no está instalado.');
    }
    administradorAccesos();
    Storage::fake('local');
    Config::set('queue.connections.fotogrametria', ['driver' => 'sync']);
    $servicio = app(FotogrametriaService::class);
    $sesion = $servicio->crearSesion(Modelado3D::factory()->create()->producto_id);

    $fotos = collect(['juguete-01.jpg', 'juguete-02.jpg', 'juguete-03.jpg', 'otro-juguete.jpg'])
        ->map(fn (string $nombre): FotografiaModelado => $servicio->guardarFoto(
            $sesion->id,
            UploadedFile::fake()->createWithContent($nombre, file_get_contents(base_path('tests/Fixtures/fotogrametria/'.$nombre))),
        ));
    expect($sesion->fresh()->referencia_foto_id)->toBe($fotos->first()->id);
    $servicio->solicitarAnalisis($sesion->id);

    expect($sesion->fresh())->status->toBe('revision')->error_message->toBeNull();
    expect($fotos->map(fn (FotografiaModelado $foto): string => $foto->fresh()->estado)->all())
        ->toBe(['compatible', 'compatible', 'compatible', 'review']);
    expect($servicio->resumen($sesion->id)['saltos'])->toBe([]);
});

test('avisa cómo iniciar el procesador cuando la validación sigue en cola', function (int $segundos, bool $aviso) {
    administradorAccesos();
    $sesion = Modelado3D::factory()->create(['status' => 'en_cola', 'updated_at' => now()->subSeconds($segundos)]);

    $componente = Livewire::test(Modelados3D::class)->call('abrirSesion', $sesion->id);

    $aviso
        ? $componente->assertSee('El procesador local todavía no tomó esta validación')
        : $componente->assertDontSee('El procesador local todavía no tomó esta validación');
})->with([
    'recién encolada' => [5, false],
    'sin procesador' => [45, true],
]);

test('reemplazar una toma conserva el diagnóstico de las demás para seguir corrigiendo', function () {
    administradorAccesos();
    Storage::fake('local');
    $sesion = Modelado3D::factory()->create(['revision' => 2, 'revision_analizada' => 2, 'status' => 'revision']);
    $fotos = FotografiaModelado::factory()->count(3)->sequence(fn ($secuencia): array => ['ranura' => $secuencia->index + 1])
        ->create(['modelado_3d_id' => $sesion->id, 'estado' => 'review', 'motivos' => ['Sin conexión verificable.']]);
    $sesion->update(['referencia_foto_id' => $fotos->first()->id]);

    $nueva = app(FotogrametriaService::class)->guardarFoto($sesion->id, UploadedFile::fake()->image('otra-toma.jpg', 800, 600), $fotos[1]->id);

    expect($fotos[2]->fresh())->estado->toBe('review')->motivos->toBe(['Sin conexión verificable.']);
    expect($nueva->estado)->toBe('pendiente');
    Livewire::test(Modelados3D::class)
        ->call('abrirSesion', $sesion->id)
        ->assertSee('La colección cambió. Vuelve a validarla.')
        ->assertViewHas('fotosPorCorregir', fn ($pendientes): bool => $pendientes->pluck('ranura')->all() === [1, 3]);
});

test('las fotos por revisar confirmadas como aptas completan la colección, pero no las rechazadas', function (string $estado, ?bool $etiqueta, bool $listo) {
    administradorAccesos();
    $sesion = Modelado3D::factory()->create(['revision' => 3, 'revision_analizada' => 3]);
    FotografiaModelado::factory()->compatible()->count(48)->sequence(fn ($secuencia): array => ['ranura' => $secuencia->index + 1])
        ->create(['modelado_3d_id' => $sesion->id]);
    $sesion->fotografias()->first()->update(['estado' => $estado, 'etiqueta_humana' => $etiqueta]);

    expect(app(FotogrametriaService::class)->resumen($sesion->id)['listo'])->toBe($listo);
})->with([
    'revisar sin confirmar' => ['review', null, false],
    'revisar confirmada apta' => ['review', true, true],
    'revisar marcada no apta' => ['review', false, false],
    'rechazada aunque se marque apta' => ['rejected', true, false],
]);

test('confirmar en bloque solo afecta a las fotos por revisar sin evaluación humana', function () {
    $admin = administradorAccesos();
    $sesion = Modelado3D::factory()->create(['revision' => 2, 'revision_analizada' => 2, 'status' => 'revision']);
    $fotos = FotografiaModelado::factory()->count(4)->sequence(fn ($secuencia): array => ['ranura' => $secuencia->index + 1])->sequence(
        ['estado' => 'review', 'etiqueta_humana' => null],
        ['estado' => 'review', 'etiqueta_humana' => false],
        ['estado' => 'rejected', 'etiqueta_humana' => null],
        ['estado' => 'compatible', 'etiqueta_humana' => null],
    )->create(['modelado_3d_id' => $sesion->id, 'vista' => 'frente']);

    Livewire::test(Modelados3D::class)
        ->call('abrirSesion', $sesion->id)
        ->assertSee('Confirmar 1 como aptas')
        ->call('confirmarFotosRevisadas')
        ->assertDispatched('alerta-producto');

    expect($fotos->map(fn (FotografiaModelado $foto): ?bool => $foto->fresh()->etiqueta_humana)->all())->toBe([true, false, null, null]);
    expect($fotos[0]->fresh())->revisado_por->toBe($admin->id)->estado->toBe('review');
    expect($sesion->fresh()->revision)->toBe(2);
});

test('no confirma fotos de un análisis desactualizado', function () {
    administradorAccesos();
    $sesion = Modelado3D::factory()->create(['revision' => 3, 'revision_analizada' => 2]);
    $foto = FotografiaModelado::factory()->create(['modelado_3d_id' => $sesion->id, 'estado' => 'review']);

    expect(fn () => app(FotogrametriaService::class)->confirmarRevisadas($sesion->id))
        ->toThrow(ValidationException::class, 'Vuelve a validar');

    expect($foto->fresh()->etiqueta_humana)->toBeNull();
});
