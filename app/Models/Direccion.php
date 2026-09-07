<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Direccion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'usuario_id',
        'nombre_referencia',
        'direccion_completa',
        'zona',
        'ciudad',
        'latitud',
        'longitud',
        'ubicacion_jsonb',
        'principal',
    ];

    protected $casts = [
        'ubicacion_jsonb' => 'array',
        'principal' => 'boolean',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
}
