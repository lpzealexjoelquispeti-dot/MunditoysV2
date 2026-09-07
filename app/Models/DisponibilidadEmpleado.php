<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DisponibilidadEmpleado extends Model
{
    use HasFactory;

    protected $fillable = [
        'usuario_id',
        'fecha',
        'disponible',
        'hora_inicio',
        'hora_fin',
    ];

    protected $casts = [
        'fecha' => 'date',
        'disponible' => 'boolean',
    ];

    public function empleado()
    {
        return $this->belongsTo(
            User::class,
            'usuario_id'
        );
    }
}
