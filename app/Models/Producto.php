<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Producto extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'categoria_id',
        'marca_id',
        'codigo_producto',
        'codigo_qr',
        'nombre',
        'descripcion',
        'precio_compra_referencia',
        'precio_venta',
        'edad_recomendada',
        'peso',
        'dimensiones',
        'estado',
    ];

    protected $casts = [
        'precio_compra_referencia' => 'decimal:2',
        'precio_venta' => 'decimal:2',
    ];

    public function getPrecioVentaAttribute($value): ?string
    {
        if ($value !== null && (float) $value > 0) {
            return number_format((float) $value, 2, '.', '');
        }

        if ($this->relationLoaded('inventarios')) {
            $primerLote = $this->inventarios
                ->flatMap(fn ($inv) => $inv->lotes ?? collect())
                ->filter(fn ($lote) => $lote->estado === 'activo' && (int) $lote->cantidad_disponible > 0 && $lote->precio_venta !== null)
                ->sortBy('fecha_ingreso')
                ->first();

            if ($primerLote) {
                return number_format((float) $primerLote->precio_venta, 2, '.', '');
            }
        }

        return $value !== null ? number_format((float) $value, 2, '.', '') : null;
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria::class)->withTrashed();
    }

    public function marca()
    {
        return $this->belongsTo(Marca::class)->withTrashed();
    }

    public function imagenes()
    {
        return $this->hasMany(ProductoImagen::class);
    }

    public function reseñas()
    {
        return $this->hasMany(Resena::class);
    }

    public function resenas()
    {
        return $this->hasMany(Resena::class);
    }

    public function modelos3D()
    {
        return $this->hasMany(Modelado3D::class);
    }

    public function proveedores()
    {
        return $this->belongsToMany(
            Proveedor::class,
            'producto_proveedor'
        )
            ->withPivot([
                'codigo_proveedor',
                'costo_compra',
                'tiempo_entrega_dias',
            ]);
    }

    public function inventarios()
    {
        return $this->hasMany(Inventario::class);
    }

    public function interacciones()
    {
        return $this->hasMany(InteraccionUsuario::class);
    }

    public function auditorias()
    {
        return $this->hasMany(
            Auditoria::class
        );
    }

    public function historialPrecios()
    {
        return $this->hasMany(
            HistorialPrecio::class
        );
    }

    public function notificaciones()
    {
        return $this->hasMany(
            Notificacion::class
        );
    }
}
