<?php

use App\Livewire\Asistente\Chat;
use App\Models\ConversacionIA;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Services\AsistenteService;
use App\Services\ClasificadorIntencionService;
use App\Services\HerramientasAsistenteService;
use App\Services\OllamaService;
use App\Services\PronosticoDemandaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Config::set('asistente.modelos.directorio', base_path('tests/Fixtures/asistente'));
    Config::set('asistente.modelos.intenciones_exportado', 'intenciones_mini.json');
});

/** @param  list<array<string, mixed>>  $turnos */
function fingirOllama(array $turnos): void
{
    $secuencia = Http::sequence();
    foreach ($turnos as $turno) {
        $secuencia->push(['message' => $turno, 'done' => true, 'prompt_eval_count' => 100, 'eval_count' => 20, 'total_duration' => 1_000_000_000]);
    }

    Http::fake([
        '*/api/embed' => fn (Request $request) => Http::response([
            'embeddings' => array_map(fn (): array => [1.0, 0.0, 0.0], (array) $request['input']),
        ]),
        '*/api/chat' => $secuencia,
    ]);
}

function clasificadorFijo(string $intencion, float $confianza): void
{
    $falso = Mockery::mock(ClasificadorIntencionService::class);
    $falso->shouldReceive('clasificar')->andReturn(['intent' => $intencion, 'confidence' => $confianza, 'alternatives' => []]);
    $falso->shouldReceive('umbral')->andReturn(0.3);
    app()->instance(ClasificadorIntencionService::class, $falso);
}

test('el clasificador en PHP reproduce las probabilidades de scikit-learn', function (): void {
    $clasificador = app(ClasificadorIntencionService::class);
    $casos = json_decode(file_get_contents(base_path('tests/Fixtures/asistente/intenciones_mini_esperado.json')), true);

    foreach ($casos as $caso) {
        $obtenidas = $clasificador->probabilidades($caso['texto']);
        foreach ($caso['probabilidades'] as $clase => $esperada) {
            expect($obtenidas[$clase])->toEqualWithDelta($esperada, 1e-5);
        }
    }

    expect($clasificador->clasificar('¿Dónde está mi pedido V-0012?')['intent'])->toBe('estado_pedido');
});

test('el asistente usa las herramientas y solo muestra productos reales que menciona', function (): void {
    clasificadorFijo('recomendacion_regalo', 0.9);
    $producto = Producto::factory()->create(['nombre' => 'Iron Man Marvel', 'precio_venta' => 350, 'edad_recomendada' => '6-8']);
    fingirOllama([
        ['role' => 'assistant', 'content' => '', 'tool_calls' => [['function' => ['name' => 'buscar_productos', 'arguments' => ['consulta' => 'iron man', 'edad' => 7]]]]],
        ['role' => 'assistant', 'content' => 'Te recomiendo **Iron Man Marvel** por 350 Bs, ideal para 7 años.'],
    ]);
    $asistente = app(AsistenteService::class);
    $conversacion = $asistente->iniciarConversacion(null, 'cliente');

    $resultado = $asistente->responder($conversacion, 'Regalo para mi sobrino de 7 años que ama Iron Man', null);

    expect($resultado['respuesta'])->toContain('Iron Man Marvel')
        ->and($resultado['herramientas'])->toBe(['buscar_productos'])
        ->and($resultado['cifras_no_verificadas'])->toBe([])
        ->and(collect($resultado['productos'])->pluck('id')->all())->toBe([$producto->id])
        ->and($conversacion->mensajes()->pluck('rol')->all())->toBe(['user', 'assistant'])
        ->and($conversacion->fresh()->metadata_jsonb['registro'][0]['intencion'])->toBe('recomendacion_regalo');

    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/chat')
        && collect($request['messages'])->contains(fn (array $m): bool => $m['role'] === 'tool' && str_contains($m['content'], 'Iron Man Marvel')));
});

test('una consulta sobre Thanos no muestra la figura astronauta del tema anterior', function (): void {
    clasificadorFijo('buscar_producto', 0.9);
    $thanos = Producto::factory()->create(['nombre' => 'Thanos Marvel', 'precio_venta' => 495]);
    Producto::factory()->create(['nombre' => 'Figura astronauta', 'precio_venta' => 105]);
    fingirOllama([
        ['role' => 'assistant', 'content' => '', 'tool_calls' => [['function' => ['name' => 'buscar_productos', 'arguments' => ['consulta' => 'thanos']]]]],
        ['role' => 'assistant', 'content' => 'Aquí tienes algunas figuras de Thanos: **Thanos Marvel** (Bs 495). Disponible: sí.'],
    ]);
    $asistente = app(AsistenteService::class);
    $conversacion = $asistente->iniciarConversacion(null, 'cliente');
    $conversacion->mensajes()->create(['rol' => 'user', 'mensaje' => '¿Tienes una figura astronauta?']);
    $conversacion->mensajes()->create(['rol' => 'assistant', 'mensaje' => 'Tenemos Figura astronauta por Bs 105.']);

    $resultado = $asistente->responder($conversacion, '¿Alguna figura de Thanos?', null);

    expect($resultado['respuesta'])->toContain('Thanos Marvel')
        ->and(collect($resultado['productos'])->pluck('id')->all())->toBe([$thanos->id]);
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/chat')
        && collect($request['messages'])->contains(fn (array $mensaje): bool => $mensaje['role'] === 'tool'
            && str_contains($mensaje['content'], 'Thanos Marvel')
            && str_contains($mensaje['content'], 'Figura astronauta')));
});

test('un nombre que es solo parte de otra palabra no genera una tarjeta', function (): void {
    clasificadorFijo('buscar_producto', 0.9);
    $producto = Producto::factory()->create(['nombre' => 'Thanos Marvel', 'precio_venta' => 495]);
    Producto::factory()->create(['nombre' => 'Thano', 'precio_venta' => 105]);
    fingirOllama([
        ['role' => 'assistant', 'content' => '', 'tool_calls' => [['function' => ['name' => 'buscar_productos', 'arguments' => ['consulta' => 'thanos']]]]],
        ['role' => 'assistant', 'content' => 'Tenemos **Thanos Marvel** por Bs 495.'],
    ]);
    $asistente = app(AsistenteService::class);

    $resultado = $asistente->responder($asistente->iniciarConversacion(null, 'cliente'), '¿Tienes Thanos?', null);

    expect(collect($resultado['productos'])->pluck('id')->all())->toBe([$producto->id]);
});

test('las cifras en bolivianos que no vienen de los datos quedan registradas como no verificadas', function (): void {
    clasificadorFijo('consultar_precio', 0.9);
    Producto::factory()->create(['nombre' => 'Thanos Marvel', 'precio_venta' => 495]);
    fingirOllama([
        ['role' => 'assistant', 'content' => '', 'tool_calls' => [['function' => ['name' => 'buscar_productos', 'arguments' => ['consulta' => 'thanos']]]]],
        ['role' => 'assistant', 'content' => 'Thanos Marvel cuesta Bs 495, y con tu presupuesto de 600 Bs te sobra. En oferta queda en 399 Bs.'],
    ]);
    $asistente = app(AsistenteService::class);

    $resultado = $asistente->responder($asistente->iniciarConversacion(null, 'cliente'), '¿Cuánto cuesta Thanos? tengo 600', null);

    expect($resultado['cifras_no_verificadas'])->toBe(['399']);
});

test('las consultas fuera de la tienda se responden sin llamar al modelo de lenguaje', function (): void {
    clasificadorFijo('fuera_de_dominio', 0.95);
    Http::fake();
    $asistente = app(AsistenteService::class);

    $resultado = $asistente->responder($asistente->iniciarConversacion(null, 'cliente'), '¿Quién ganó el partido?', null);

    expect($resultado['respuesta'])->toContain('Solo puedo ayudarte con temas de MundiToys');
    Http::assertNothingSent();
});

test('un cliente no puede pedir datos internos aunque el modelo intente usar esa herramienta', function (): void {
    $herramientas = app(HerramientasAsistenteService::class);
    $cliente = User::factory()->create();

    $nombres = collect($herramientas->definiciones($cliente))->pluck('function.name');

    expect($nombres)->not->toContain('pronosticar_demanda')->not->toContain('resumen_ventas')
        ->and($herramientas->ejecutar('resumen_ventas', [], $cliente))->toBe(['error' => 'Herramienta no disponible para este usuario.'])
        ->and(collect($herramientas->definiciones(administradorAccesos()))->pluck('function.name'))
        ->toContain('pronosticar_demanda', 'revisar_inventario', 'resumen_ventas');
});

test('el estado de pedidos solo muestra los pedidos del propio cliente', function (): void {
    $cliente = User::factory()->create();
    $otro = User::factory()->create();
    Venta::factory()->create(['cliente_id' => $cliente->id, 'numero_venta' => 'V-PROPIA']);
    Venta::factory()->create(['cliente_id' => $otro->id, 'numero_venta' => 'V-AJENA']);
    $herramientas = app(HerramientasAsistenteService::class);

    $propios = $herramientas->ejecutar('estado_pedido', [], $cliente);
    $ajeno = $herramientas->ejecutar('estado_pedido', ['numero_venta' => 'V-AJENA'], $cliente);

    expect(collect($propios['pedidos'])->pluck('numero')->all())->toBe(['V-PROPIA'])
        ->and($ajeno['pedidos'])->toBe([])
        ->and($herramientas->ejecutar('estado_pedido', [], null)['nota'])->toContain('iniciar sesión');
});

test('el presupuesto se reparte primero a los productos que se agotan antes', function (): void {
    $reparto = app(PronosticoDemandaService::class)->repartirPresupuesto(collect([
        ['producto' => 'Thanos', 'unidades_sugeridas' => 4, 'costo_unitario' => 300.0, 'semanas_cobertura' => 5.0],
        ['producto' => 'Iron Man', 'unidades_sugeridas' => 6, 'costo_unitario' => 200.0, 'semanas_cobertura' => 0.5],
        ['producto' => 'Colossus', 'unidades_sugeridas' => 0, 'costo_unitario' => 250.0, 'semanas_cobertura' => 1.0],
    ]), 1500);

    expect($reparto['compras'])->toBe([
        ['producto' => 'Iron Man', 'unidades' => 6, 'costo' => 1200.0, 'cubre_sugerido' => true],
        ['producto' => 'Thanos', 'unidades' => 1, 'costo' => 300.0, 'cubre_sugerido' => false],
    ])->and($reparto['usado'])->toBe(1500.0);
});

test('el pronóstico sugiere reponer lo que falta para cubrir la demanda estimada', function (): void {
    $producto = Producto::factory()->create(['precio_venta' => 350, 'precio_compra_referencia' => 200]);
    Process::fake(['*pronostico.py*' => Process::result(json_encode([
        'event' => 'result', 'model_version' => 'pronostico-v3',
        'series' => [['id' => $producto->id, 'total' => 10.0, 'real_weeks' => 3, 'simulated_weeks' => 49,
            'forecast' => [['week' => '2026-10-05', 'units' => 4.0], ['week' => '2026-10-12', 'units' => 6.0]]]],
    ]))]);
    Config::set('asistente.modelos.pronostico', 'pronostico_mini.txt');
    file_put_contents(base_path('tests/Fixtures/asistente/pronostico_mini.txt'), 'modelo de prueba');

    try {
        $pronostico = app(PronosticoDemandaService::class)->pronosticar(2, 1000);
    } finally {
        @unlink(base_path('tests/Fixtures/asistente/pronostico_mini.txt'));
    }

    expect($pronostico['historia_simulada'])->toBeTrue()
        ->and($pronostico['productos'][0])->toMatchArray(['demanda_estimada' => 10.0, 'stock_actual' => 0, 'unidades_sugeridas' => 12, 'costo_reposicion' => 2400.0])
        ->and($pronostico['semana_pico'])->toBe(['semana' => '2026-10-12', 'unidades' => 6.0])
        ->and($pronostico['presupuesto']['compras'][0]['unidades'])->toBe(5);
});

test('el chat muestra el mensaje al instante y luego la respuesta del asistente', function (): void {
    $falso = Mockery::mock(AsistenteService::class);
    $falso->shouldReceive('iniciarConversacion')->andReturnUsing(fn (?User $u, string $canal) => ConversacionIA::query()->create(['user_id' => $u?->id]));
    $falso->shouldReceive('responder')->once()->andReturn(['respuesta' => 'Hola, ¿qué buscas?', 'productos' => [], 'intencion' => null,
        'herramientas' => [], 'cifras_no_verificadas' => [], 'duracion_ms' => 5]);
    app()->instance(AsistenteService::class, $falso);

    Livewire::test(Chat::class)
        ->set('texto', 'Hola')
        ->call('enviar')
        ->assertSet('pendiente', 'Hola')
        ->assertSet('texto', '')
        ->call('responder')
        ->assertSet('pendiente', null)
        ->assertSet('mensajes', [['rol' => 'user', 'texto' => 'Hola'], ['rol' => 'assistant', 'texto' => 'Hola, ¿qué buscas?', 'productos' => []]]);
});

test('el chat no reabre la conversación de otro usuario', function (): void {
    $ajena = ConversacionIA::query()->create(['user_id' => User::factory()->create()->id]);
    $ajena->mensajes()->create(['rol' => 'user', 'mensaje' => 'mensaje privado']);
    session()->put('asistente.conversacion.cliente', $ajena->id);

    Livewire::actingAs(User::factory()->create())->test(Chat::class)
        ->assertSet('conversacionId', null)
        ->assertSet('mensajes', []);
});

test('un cliente no puede abrir el canal administrativo del chat', function (): void {
    Livewire::actingAs(User::factory()->create())->test(Chat::class, ['canal' => 'admin'])
        ->assertSet('canal', 'cliente');
});

test('la respuesta de Ollama llega por partes y se muestra mientras se escribe', function (): void {
    $lineas = collect([
        ['message' => ['role' => 'assistant', 'content' => 'Hola, '], 'done' => false],
        ['message' => ['role' => 'assistant', 'content' => 'soy Mundi.'], 'done' => false],
        ['message' => ['role' => 'assistant', 'content' => ''], 'done' => true, 'prompt_eval_count' => 50, 'eval_count' => 4, 'total_duration' => 2_000_000_000],
    ])->map(fn (array $linea): string => json_encode($linea))->implode("\n");
    Http::fake(['*/api/chat' => Http::response($lineas)]);
    $parciales = [];

    $turno = app(OllamaService::class)->conversar([['role' => 'user', 'content' => 'hola']], alEscribir: function (string $texto) use (&$parciales): void {
        $parciales[] = $texto;
    });

    expect($turno['message'])->toBe(['role' => 'assistant', 'content' => 'Hola, soy Mundi.'])
        ->and($turno['output_tokens'])->toBe(4)
        ->and($turno['duration_ms'])->toBe(2000)
        ->and($parciales)->toBe(['Hola, ', 'Hola, soy Mundi.']);
});

test('si nada cumple el presupuesto la búsqueda devuelve las alternativas con el motivo', function (): void {
    fingirOllama([]);
    Producto::factory()->create(['nombre' => 'Thanos Marvel', 'precio_venta' => 495, 'edad_recomendada' => '13+']);

    $resultado = app(HerramientasAsistenteService::class)->ejecutar('buscar_productos', ['consulta' => 'figura de colección', 'precio_max' => 400], null);

    expect($resultado['resultados'])->toBe([])
        ->and($resultado['alternativas_que_no_cumplen'][0]['nombre'])->toBe('Thanos Marvel')
        ->and($resultado['alternativas_que_no_cumplen'][0]['no_cumple'])->toBe(['supera el presupuesto de 400 Bs']);
});

test('los productos del catálogo que el modelo nombra sin haberlos consultado se registran como no verificados', function (): void {
    clasificadorFijo('recomendacion_regalo', 0.9);
    Producto::factory()->create(['nombre' => 'Thanos Marvel', 'precio_venta' => 495]);
    Producto::factory()->create(['nombre' => 'Iron Man Marvel', 'precio_venta' => 350]);
    Http::fake([
        // Iron Man no se parece a la consulta: la búsqueda no lo devuelve.
        '*/api/embed' => fn (Request $request) => Http::response(['embeddings' => array_map(
            fn (string $texto): array => str_contains($texto, 'Iron') ? [0.0, 1.0] : [1.0, 0.0], (array) $request['input'],
        )]),
        '*/api/chat' => Http::sequence()
            ->push(['message' => ['role' => 'assistant', 'content' => '', 'tool_calls' => [['function' => ['name' => 'buscar_productos', 'arguments' => ['consulta' => 'thanos', 'precio_max' => 100]]]]], 'done' => true])
            ->push(['message' => ['role' => 'assistant', 'content' => 'No hay nada en ese presupuesto, pero Iron Man Marvel podría gustarle.'], 'done' => true]),
    ]);
    $asistente = app(AsistenteService::class);

    $resultado = $asistente->responder($asistente->iniciarConversacion(null, 'cliente'), 'algo de thanos hasta 100', null);

    expect($resultado['productos_no_verificados'])->toBe(['Iron Man Marvel'])
        ->and($resultado['productos'])->toBe([]);
});

test('la edad o el presupuesto que el usuario nunca escribió no llegan a la herramienta', function (): void {
    clasificadorFijo('recomendacion_regalo', 0.9);
    Producto::factory()->create(['nombre' => 'Thanos Marvel', 'precio_venta' => 495, 'edad_recomendada' => '13+']);
    fingirOllama([
        ['role' => 'assistant', 'content' => '', 'tool_calls' => [['function' => ['name' => 'buscar_productos', 'arguments' => ['consulta' => 'figuras', 'edad' => 40, 'precio_max' => 400]]]]],
        ['role' => 'assistant', 'content' => 'Te sugiero Thanos Marvel.'],
    ]);
    $asistente = app(AsistenteService::class);
    $conversacion = $asistente->iniciarConversacion(null, 'cliente');
    $conversacion->mensajes()->create(['rol' => 'user', 'mensaje' => 'Algo para mi sobrino, tengo 400 Bs']);

    $asistente->responder($conversacion, 'Ahora algo para mi papá que colecciona figuras', null);

    $registro = $conversacion->fresh()->metadata_jsonb['registro'][0];
    expect($registro['argumentos_descartados'])->toBe([['edad' => 40], ['precio_max' => 400]]);
    Http::assertSent(fn (Request $request): bool => collect($request['messages'] ?? [])
        ->contains(fn (array $m): bool => $m['role'] === 'tool' && str_contains($m['content'], 'Thanos Marvel')
            && ! str_contains($m['content'], 'supera el presupuesto de 400')));
});

test('si el modelo arma mal una llamada a herramienta se reintenta y luego responde sin herramientas', function (): void {
    clasificadorFijo('recomendacion_regalo', 0.9);
    $error = ['error' => "Value looks like object, but can't find closing '}' symbol"];
    Http::fake(['*/api/chat' => Http::sequence()
        ->push($error, 500)
        ->push($error, 500)
        ->push(['message' => ['role' => 'assistant', 'content' => '¿Para qué edad buscas el regalo?'], 'done' => true])]);
    $asistente = app(AsistenteService::class);
    $conversacion = $asistente->iniciarConversacion(null, 'cliente');

    $resultado = $asistente->responder($conversacion, 'quiero un regalo', null);

    expect($resultado['respuesta'])->toBe('¿Para qué edad buscas el regalo?')
        ->and($conversacion->fresh()->metadata_jsonb['registro'][0]['reintentos'])->toBe(2);
    Http::assertSentCount(3);
    Http::assertSent(fn (Request $request): bool => ! isset($request['tools']) && count($request['messages']) === 2);
});

test('una herramienta sin argumentos se reenvía a Ollama como objeto JSON vacío', function (): void {
    clasificadorFijo('admin_inventario', 0.9);
    $admin = administradorAccesos();
    fingirOllama([
        ['role' => 'assistant', 'content' => '', 'tool_calls' => [['function' => ['name' => 'revisar_inventario', 'arguments' => []]]]],
        ['role' => 'assistant', 'content' => 'No hay productos agotados.'],
    ]);
    $asistente = app(AsistenteService::class);

    $resultado = $asistente->responder($asistente->iniciarConversacion($admin, 'admin'), '¿Qué está por agotarse?', $admin);

    expect($resultado['respuesta'])->toBe('No hay productos agotados.');
    Http::assertSent(fn (Request $request): bool => str_contains($request->body(), '"arguments":{}'));
});
