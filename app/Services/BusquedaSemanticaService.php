<?php

namespace App\Services;

use App\Models\BaseConocimientoIA;
use App\Models\Producto;
use App\Models\VectorIA;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Búsqueda por significado (RAG): compara el vector de la pregunta con los de productos y conocimiento.
 *
 * El catálogo es pequeño, así que la similitud coseno se calcula en PHP y no hace falta pgvector.
 */
class BusquedaSemanticaService
{
    public const TIPO_PRODUCTO = 'producto';

    public const TIPO_CONOCIMIENTO = 'conocimiento';

    public function __construct(private OllamaService $ollama) {}

    /**
     * @return list<array{id: int, similitud: float}>
     */
    public function buscar(string $consulta, string $tipo, ?int $limite = null): array
    {
        $this->sincronizarSiHaceFalta();
        $vectores = VectorIA::query()->where('tipo', $tipo)->get(['referencia_id', 'vector']);
        if ($vectores->isEmpty() || trim($consulta) === '') {
            return [];
        }

        $pregunta = $this->ollama->vectorizar([$consulta])[0];
        $minima = (float) config('asistente.busqueda.similitud_minima');

        return $vectores
            ->map(fn (VectorIA $v): array => ['id' => (int) $v->referencia_id, 'similitud' => round($this->coseno($pregunta, $v->vector), 4)])
            ->filter(fn (array $r): bool => $r['similitud'] >= $minima)
            ->sortByDesc('similitud')
            ->take($limite ?? (int) config('asistente.busqueda.resultados'))
            ->values()
            ->all();
    }

    /**
     * Recalcula solo los vectores cuyo texto cambió y borra los de registros que ya no existen.
     *
     * @return array{actualizados: int, eliminados: int}
     */
    public function sincronizar(): array
    {
        $modelo = (string) config('asistente.ollama.modelo_embeddings');
        $textos = $this->textos();
        $existentes = VectorIA::query()->get(['id', 'tipo', 'referencia_id', 'huella'])
            ->keyBy(fn (VectorIA $v): string => $v->tipo.':'.$v->referencia_id);

        $pendientes = $textos->filter(function (array $item, string $clave) use ($existentes, $modelo): bool {
            return $existentes->get($clave)?->huella !== sha1($modelo.'|'.$item['texto']);
        });

        foreach ($pendientes->chunk(16) as $lote) {
            $vectores = $this->ollama->vectorizar($lote->pluck('texto')->all());
            foreach ($lote->values() as $i => $item) {
                VectorIA::query()->updateOrCreate(
                    ['tipo' => $item['tipo'], 'referencia_id' => $item['id']],
                    ['modelo' => $modelo, 'huella' => sha1($modelo.'|'.$item['texto']), 'vector' => $vectores[$i]],
                );
            }
        }

        $sobrantes = $existentes->keys()->diff($textos->keys());
        VectorIA::query()->whereIn('id', $sobrantes->map(fn (string $k): int => $existentes[$k]->id))->delete();
        Cache::put('asistente:vectores-sincronizados', true, now()->addMinutes(10));

        return ['actualizados' => $pendientes->count(), 'eliminados' => $sobrantes->count()];
    }

    /**
     * Texto que representa a un producto: todo lo que un cliente podría preguntar sobre él.
     */
    public function textoProducto(Producto $producto): string
    {
        return collect([
            $producto->nombre,
            $producto->categoria?->nombre ? 'Categoría: '.$producto->categoria->nombre : null,
            $producto->marca?->nombre ? 'Marca: '.$producto->marca->nombre : null,
            $producto->edad_recomendada ? 'Edad recomendada: '.$producto->edad_recomendada.' años' : null,
            $producto->dimensiones ? 'Medidas: '.$producto->dimensiones : null,
            $producto->descripcion,
        ])->filter()->implode('. ');
    }

    private function sincronizarSiHaceFalta(): void
    {
        if (! Cache::has('asistente:vectores-sincronizados')) {
            $this->sincronizar();
        }
    }

    /**
     * @return Collection<string, array{tipo: string, id: int, texto: string}>
     */
    private function textos(): Collection
    {
        $productos = Producto::query()->where('estado', 'activo')->with(['categoria', 'marca'])->get()
            ->mapWithKeys(fn (Producto $p): array => [self::TIPO_PRODUCTO.':'.$p->id => [
                'tipo' => self::TIPO_PRODUCTO, 'id' => $p->id, 'texto' => $this->textoProducto($p),
            ]]);
        $conocimiento = BaseConocimientoIA::query()->where('estado', true)->get()
            ->mapWithKeys(fn (BaseConocimientoIA $c): array => [self::TIPO_CONOCIMIENTO.':'.$c->id => [
                'tipo' => self::TIPO_CONOCIMIENTO, 'id' => $c->id, 'texto' => $c->titulo.'. '.$c->contenido,
            ]]);

        return $productos->merge($conocimiento);
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private function coseno(array $a, array $b): float
    {
        $producto = $normaA = $normaB = 0.0;
        foreach ($a as $i => $valor) {
            $producto += $valor * ($b[$i] ?? 0.0);
            $normaA += $valor * $valor;
            $normaB += ($b[$i] ?? 0.0) ** 2;
        }

        return $normaA > 0 && $normaB > 0 ? $producto / sqrt($normaA * $normaB) : 0.0;
    }
}
