<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoteInventario extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'lotes_inventario';

    protected $fillable = [
        'inventario_id',
        'proveedor_id',
        'codigo_lote',
        'cantidad_inicial',
        'cantidad_disponible',
        'costo_unitario',
        'fecha_ingreso',
        'fecha_vencimiento',
        'estado',
        'precio_venta',
        'margen_porcentaje',
        'clave_ingreso',
        'registrado_por',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'fecha_vencimiento' => 'date',
        'costo_unitario' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'margen_porcentaje' => 'decimal:2',
        'cantidad_inicial' => 'integer',
        'cantidad_disponible' => 'integer',
        'registrado_por' => 'integer',
        'inventario_id' => 'integer',
    ];

    public function inventario(): BelongsTo
    {
        return $this->belongsTo(Inventario::class)->withTrashed();
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class)->withTrashed();
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(
            MovimientoInventario::class,
            'lote_id'
        );
    }
}
