<?php

namespace App\Livewire\Asistente;

use App\Models\ConversacionIA;
use App\Models\InteraccionUsuario;
use App\Models\MensajeIA;
use App\Services\AsistenteService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Chat flotante del asistente. Canal 'cliente' en la tienda y 'admin' en el panel (con herramientas internas).
 */
class Chat extends Component
{
    #[Locked]
    public string $canal = 'cliente';

    #[Locked]
    public ?int $conversacionId = null;

    public string $texto = '';

    /** @var list<array{rol: string, texto: string, productos?: list<array<string, mixed>>, error?: bool}> */
    #[Locked]
    public array $mensajes = [];

    #[Locked]
    public ?string $pendiente = null;

    public function mount(string $canal = 'cliente'): void
    {
        $this->canal = $canal === 'admin' && Auth::user()?->can('acceder panel') ? 'admin' : 'cliente';
        $conversacion = $this->conversacionGuardada();
        if ($conversacion) {
            $this->conversacionId = $conversacion->id;
            $this->mensajes = $conversacion->mensajes()->latest('id')->take(20)->get()->reverse()
                ->map(fn (MensajeIA $m): array => ['rol' => $m->rol, 'texto' => $m->mensaje,
                    'reporte_mensaje_id' => collect($conversacion->metadata_jsonb['reportes'] ?? [])->contains('mensaje_id', $m->id) ? $m->id : null,
                    'mensaje_id' => $m->id,
                    'puede_valorar' => $m->rol === 'assistant' && Auth::check() && collect($conversacion->metadata_jsonb['registro'] ?? [])
                        ->contains(fn (array $r): bool => ($r['mensaje_id'] ?? null) === $m->id && ! ($r['respuesta_protegida'] ?? true)),
                ])->values()->all();
        }
    }

    /**
     * Muestra al instante el mensaje del usuario y pide la respuesta en una segunda llamada.
     */
    public function enviar(): void
    {
        $texto = Str::limit(trim($this->texto), (int) config('asistente.conversacion.max_caracteres_mensaje'), '');
        if ($texto === '' || $this->pendiente !== null) {
            return;
        }

        $clave = 'asistente:'.(Auth::id() ?? request()->ip());
        if (RateLimiter::tooManyAttempts($clave, (int) config('asistente.conversacion.mensajes_por_minuto'))) {
            $this->mensajes[] = ['rol' => 'assistant', 'texto' => 'Estás enviando muchos mensajes seguidos. Espera un momento, por favor.', 'error' => true];

            return;
        }
        RateLimiter::hit($clave, 60);

        $this->mensajes[] = ['rol' => 'user', 'texto' => $texto];
        $this->pendiente = $texto;
        $this->texto = '';
        $this->js('$wire.responder()');
    }

    public function usarSugerencia(string $sugerencia): void
    {
        $this->texto = $sugerencia;
        $this->enviar();
    }

    public function responder(AsistenteService $asistente): void
    {
        $texto = $this->pendiente;
        if ($texto === null) {
            return;
        }

        set_time_limit((int) config('asistente.conversacion.tiempo_maximo_respuesta'));

        try {
            $conversacion = $this->conversacion($asistente);
            $resultado = $asistente->responder($conversacion, $texto, Auth::user(), function (string $parcial): void {
                $this->stream(to: 'respuesta-'.$this->canal, content: nl2br(e($parcial)), replace: true);
            });
            $respuesta = ['rol' => 'assistant', 'texto' => $resultado['respuesta'], 'productos' => $resultado['productos']];
            if (isset($resultado['mensaje_id'])) {
                $respuesta += ['mensaje_id' => $resultado['mensaje_id'], 'reporte_mensaje_id' => $resultado['reporte_mensaje_id'] ?? null,
                    'puede_valorar' => Auth::check() && ($resultado['puede_valorar'] ?? false)];
            }
            $this->mensajes[] = $respuesta;
        } catch (RuntimeException $error) {
            report($error);
            $this->mensajes[] = ['rol' => 'assistant', 'texto' => 'El asistente no pudo responder en este momento. Inténtalo de nuevo.', 'error' => true];
        } catch (Throwable $error) {
            report($error);
            $this->mensajes[] = ['rol' => 'assistant', 'texto' => 'Tuve un problema al responder. Inténtalo de nuevo en un momento.', 'error' => true];
        } finally {
            $this->pendiente = null;
        }
    }

    public function nuevaConversacion(): void
    {
        session()->forget($this->claveSesion());
        $this->reset('conversacionId', 'mensajes', 'texto', 'pendiente');
    }

    public function descargarReporte(int $mensajeId, AsistenteService $asistente): StreamedResponse
    {
        $conversacion = $this->conversacionGuardada();
        abort_unless($conversacion && $conversacion->id === $this->conversacionId, 403);
        $texto = $asistente->textoReporte($conversacion, $mensajeId, Auth::user());

        return response()->streamDownload(function () use ($texto): void {
            echo $texto;
        }, 'reporte-munditoys-'.$mensajeId.'.txt', ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public function valorarRespuesta(int $mensajeId, bool $util): void
    {
        $conversacion = $this->conversacionGuardada();
        abort_unless(Auth::check() && $conversacion && $conversacion->id === $this->conversacionId, 403);
        $registro = collect($conversacion->metadata_jsonb['registro'] ?? [])->firstWhere('mensaje_id', $mensajeId);
        abort_unless($registro && ! ($registro['respuesta_protegida'] ?? true), 403);
        $mensaje = $conversacion->mensajes()->whereKey($mensajeId)->where('rol', 'assistant')->firstOrFail();
        $datos = ['mensaje_id' => $mensaje->id, 'util' => $util, 'modelo' => $mensaje->modelo, 'apto_entrenamiento_automatico' => false];
        $valoracion = InteraccionUsuario::query()->where('user_id', Auth::id())->where('tipo_interaccion', 'asistente_evaluacion')->where('datos_jsonb->mensaje_id', $mensajeId)->first();
        if ($valoracion) {
            $valoracion->update(['datos_jsonb' => $datos]);
        } else {
            InteraccionUsuario::query()->create(['user_id' => Auth::id(), 'tipo_interaccion' => 'asistente_evaluacion', 'datos_jsonb' => $datos]);
        }
        foreach ($this->mensajes as &$respuesta) {
            if (($respuesta['mensaje_id'] ?? null) === $mensajeId) {
                $respuesta['valoracion'] = $util;
            }
        }
    }

    public function render(): View
    {
        return view('livewire.asistente.chat', [
            'sugerencias' => $this->canal === 'admin'
                ? array_values(array_filter([
                    '¿Qué puedes hacer?',
                    Auth::user()?->can('gestionar inventario') ? '¿Qué productos están por agotarse?' : null,
                    Auth::user()?->can('gestionar inventario') ? 'Tengo 3000 Bs, ¿qué conviene comprar para diciembre?' : null,
                    Auth::user()?->can('administrar accesos') ? '¿Cuánto vendimos este mes?' : null,
                    Auth::user()?->can('crear ventas') ? 'Genera un reporte de ventas de este mes' : null,
                    Auth::user()?->can('acceder logistica') ? 'Genera un reporte de mis entregas de hoy' : null,
                ]))
                : ['¿Qué puedes hacer?', 'Regalo de dinosaurios para 7 años hasta 200 Bs', '¿Cómo funcionan los envíos?'],
        ]);
    }

    private function conversacion(AsistenteService $asistente): ConversacionIA
    {
        $conversacion = $this->conversacionGuardada();
        if (! $conversacion) {
            if ($this->conversacionId !== null) {
                $this->mensajes = [['rol' => 'user', 'texto' => $this->pendiente ?? '']];
            }
            $conversacion = $asistente->iniciarConversacion(Auth::user(), $this->canal);
            session()->put($this->claveSesion(), $conversacion->id);
        }
        $this->conversacionId = $conversacion->id;

        return $conversacion;
    }

    /**
     * La conversación se recuerda en la sesión y solo se reabre si pertenece al mismo usuario.
     */
    private function conversacionGuardada(): ?ConversacionIA
    {
        $id = session($this->claveSesion());

        $conversacion = $id ? ConversacionIA::query()->whereKey($id)->where('user_id', Auth::id())->first() : null;

        return $conversacion && app(AsistenteService::class)->puedeUsarConversacion($conversacion, Auth::user()) ? $conversacion : null;
    }

    private function claveSesion(): string
    {
        return 'asistente.conversacion.'.$this->canal;
    }
}
