<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiaNoLaborable extends Model
{
    use HasFactory;

    protected $fillable = [
        'fecha',
        'descripcion',
        'estado',
    ];

    protected $casts = [
        'fecha' => 'date',
        'estado' => 'boolean',
    ];
}
