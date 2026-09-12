<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero_venta',
        'cliente_id',
        'usuario_vendedor_id',
        'tipo_venta',
        'tipo_entrega',
        'estado',
        'subtotal',
        'descuento',
        'total',
        'costo_envio',
        'cotizacion_envio',
        'fecha_venta',
    ];

    protected $casts = [
        'costo_envio' => 'decimal:2',
        'cotizacion_envio' => 'array',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
        'fecha_venta' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(
            User::class,
            'cliente_id'
        );
    }

    public function vendedor()
    {
        return $this->belongsTo(
            User::class,
            'usuario_vendedor_id'
        );
    }

    public function detalles()
    {
        return $this->hasMany(
            DetalleVenta::class
        );
    }

    public function pago()
    {
        return $this->hasOne(Pago::class);
    }

    public function entrega()
    {
        return $this->hasOne(Entrega::class);
    }
}
