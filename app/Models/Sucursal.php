<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sucursal extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'sucursales';

    protected $fillable = [
        'nombre',
        'direccion',
        'zona',
        'ciudad',
        'latitud',
        'longitud',
        'ubicacion_jsonb',
        'telefono',
        'estado',
    ];

    protected $casts = [
        'latitud' => 'decimal:7',
        'longitud' => 'decimal:7',
        'ubicacion_jsonb' => 'array',
        'estado' => 'boolean',
    ];

    public function almacenes(): HasMany
    {
        return $this->hasMany(Almacen::class);
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class, 'sucursal_origen_id');
    }
}
