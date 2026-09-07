<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inventario extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'producto_id',
        'almacen_id',
        'stock_minimo',
        'historial_estado',
    ];

    protected $casts = ['stock_minimo' => 'integer', 'historial_estado' => 'array'];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class)->withTrashed();
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class)->withTrashed();
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(LoteInventario::class);
    }
}
