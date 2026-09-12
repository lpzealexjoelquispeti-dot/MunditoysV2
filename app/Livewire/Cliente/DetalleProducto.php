<?php

namespace App\Livewire\Cliente;

use App\Services\CarritoService;
use App\Services\CatalogoService;
use App\Services\ResenaCompraService;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Detalle del Juguete - MundiToys')]
class DetalleProducto extends Component
{
    public string $codigo = '';

    public int $cantidad = 1;

    public string $vistaActiva = 'fotos'; // 'fotos' | '3d'

    public ?string $fotoSeleccionada = null;

    public string $calificacion = '';

    public string $comentario = '';

    public function guardarResena(CatalogoService $catalogo, ResenaCompraService $resenas): void
    {
        abort_unless(auth()->check(), 403, 'Inicia sesión para escribir una reseña.');
        $resenas->guardar(auth()->user(), $catalogo->obtenerPorCodigo($this->codigo), $this->calificacion, $this->comentario);
        $this->reset('calificacion', 'comentario');
        session()->flash('resena_guardada', 'Tu reseña se publicó correctamente. Gracias por compartir tu experiencia.');
    }

    public function mount(string $codigo, CatalogoService $catalogoService): void
    {
        $this->codigo = $codigo;
        $producto = $catalogoService->obtenerPorCodigo($this->codigo);

        $primeraFoto = $producto->imagenes->sortBy('orden')->first();
        if ($primeraFoto) {
            $this->fotoSeleccionada = $primeraFoto->ruta_archivo;
        }
    }

    public function seleccionarFoto(string $ruta): void
    {
        $this->fotoSeleccionada = $ruta;
        $this->vistaActiva = 'fotos';
    }

    public function activarVista3D(): void
    {
        $this->vistaActiva = '3d';
    }

    public function activarVistaFotos(): void
    {
        $this->vistaActiva = 'fotos';
    }

    public function incrementar(int $stockMaximo = 99): void
    {
        if ($this->cantidad < max(1, $stockMaximo)) {
            $this->cantidad++;
        }
    }

    public function decrementar(): void
    {
        if ($this->cantidad > 1) {
            $this->cantidad--;
        }
    }

    public function agregarAlCarrito(CarritoService $carritoService, CatalogoService $catalogoService): void
    {
        $producto = $catalogoService->obtenerPorCodigo($this->codigo);
        $stock = $catalogoService->stockDisponible($producto);

        if ($stock > 0 && $this->cantidad > $stock) {
            $this->cantidad = $stock;
        }

        $carritoService->agregar($producto, $this->cantidad);

        $this->dispatch('carrito-actualizado');
        $this->dispatch('animar-al-carrito', [
            'nombre' => $producto->nombre,
            'cantidad' => $this->cantidad,
            'imagen' => $this->fotoSeleccionada ? asset('storage/'.$this->fotoSeleccionada) : null,
        ]);
    }

    public function comprarAhora(CarritoService $carritoService, CatalogoService $catalogoService)
    {
        $this->agregarAlCarrito($carritoService, $catalogoService);

        return $this->redirect(route('tienda.checkout'), navigate: true);
    }

    public function render(CatalogoService $catalogoService): View
    {
        $producto = $catalogoService->obtenerPorCodigo($this->codigo);
        $stock = $catalogoService->stockDisponible($producto);
        $relacionados = $catalogoService->relacionados($producto, 4);

        $calificaciones = $producto->resenas->pluck('calificacion');
        $promedioCalificacion = $calificaciones->isNotEmpty() ? round($calificaciones->avg(), 1) : 0;
        $totalResenas = $producto->resenas->count();

        $modeloGlb = $producto->modelos3D->whereNotNull('modelo_glb_path')->first();

        return view('livewire.cliente.detalle-producto', [
            'producto' => $producto,
            'stock' => $stock,
            'relacionados' => $relacionados,
            'promedioCalificacion' => $promedioCalificacion,
            'totalResenas' => $totalResenas,
            'modeloGlb' => $modeloGlb,
            'puedeResenar' => app(ResenaCompraService::class)->puedeResenar(auth()->user(), $producto),
        ]);
    }
}
