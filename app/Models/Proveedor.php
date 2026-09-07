<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proveedor extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'proveedores';

    protected $fillable = [
        'empresa',
        'nombre_contacto',
        'telefono',
        'email',
        'direccion',
        'nit',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    public function productos(): BelongsToMany
    {
        return $this->belongsToMany(
            Producto::class,
            'producto_proveedor'
        )
            ->withPivot([
                'codigo_proveedor',
                'costo_compra',
                'tiempo_entrega_dias',
            ]);
    }

    public function lotes(): HasMany
    {
        return $this->hasMany(LoteInventario::class);
    }
}
