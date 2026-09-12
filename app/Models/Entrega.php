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
        'motivo_cancelacion',
        'notas_despacho',
        'cancelado_at',
        'entregado_at',
        'destino_snapshot',
    ];

    protected $casts = [
        'destino_snapshot' => 'array',
        'fecha_programada' => 'date',
        'cancelado_at' => 'datetime',
        'entregado_at' => 'datetime',
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

    public function scopeDelDia($query, string $fecha)
    {
        return $query->whereDate('fecha_programada', $fecha);
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeEnCamino($query)
    {
        return $query->where('estado', 'en_camino');
    }

    public function scopeEntregados($query)
    {
        return $query->where('estado', 'entregado');
    }

    public function scopeCancelados($query)
    {
        return $query->where('estado', 'cancelado');
    }
}
