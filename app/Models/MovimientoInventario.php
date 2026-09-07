<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientoInventario extends Model
{
    use HasFactory;

    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'lote_id',
        'usuario_id',
        'tipo_movimiento',
        'cantidad',
        'cantidad_anterior',
        'cantidad_nueva',
        'motivo',
        'referencia_tipo',
        'referencia_id',
    ];

    public function lote()
    {
        return $this->belongsTo(
            LoteInventario::class,
            'lote_id'
        );
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
}
