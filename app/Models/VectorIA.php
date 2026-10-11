<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VectorIA extends Model
{
    protected $table = 'vectores_ia';

    protected $fillable = [
        'tipo',
        'referencia_id',
        'modelo',
        'huella',
        'vector',
    ];

    protected $casts = [
        'vector' => 'array',
    ];
}
