<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Entrega extends Model
{
    use HasFactory;

    protected $fillable = [
        'venta_id',
        'direccion_id',
        'empleado_id',
        'sucursal_origen_id',
        'fecha_programada',
        'hora_inicio',
        'hora_fin',
        'estado',
    ];

    protected $casts = [
        'fecha_programada' => 'date',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function direccion()
    {
        return $this->belongsTo(Direccion::class);
    }

    public function empleado()
    {
        return $this->belongsTo(
            User::class,
            'empleado_id'
        );
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(
            Sucursal::class,
            'sucursal_origen_id'
        )->withTrashed();
    }
}
