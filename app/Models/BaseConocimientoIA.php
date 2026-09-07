<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BaseConocimientoIA extends Model
{
    use HasFactory;

    protected $table = 'base_conocimiento_ia';

    protected $fillable = [
        'titulo',
        'contenido',
        'categoria',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];
}
