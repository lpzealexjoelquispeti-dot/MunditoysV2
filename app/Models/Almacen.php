<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Almacen extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'almacenes';

    protected $fillable = [
        'sucursal_id',
        'nombre',
        'tipo',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class)->withTrashed();
    }

    public function inventarios(): HasMany
    {
        return $this->hasMany(Inventario::class);
    }
}
