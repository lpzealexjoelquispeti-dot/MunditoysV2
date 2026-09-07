<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HorarioEmpleado extends Model
{
    use HasFactory;

    protected $fillable = [
        'usuario_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
        'estado',
    ];

    protected $casts = [
        'estado' => 'boolean',
    ];

    public function empleado()
    {
        return $this->belongsTo(
            User::class,
            'usuario_id'
        );
    }
}
