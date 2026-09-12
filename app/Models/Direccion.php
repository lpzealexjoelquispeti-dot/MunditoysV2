<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Direccion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'direcciones';

    protected $fillable = [
        'user_id',
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
        'latitud' => 'float',
        'longitud' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->user();
    }
}
