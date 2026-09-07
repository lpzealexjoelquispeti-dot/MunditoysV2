<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HistorialPrecio extends Model
{
    use HasFactory;

    protected $table = 'historial_precios';

    protected $fillable = [
        'producto_id',
        'precio_anterior',
        'precio_nuevo',
        'usuario_id',
        'motivo',
    ];

    protected $casts = [
        'precio_anterior' => 'decimal:2',
        'precio_nuevo' => 'decimal:2',
    ];

    public function producto()
    {
        return $this->belongsTo(
            Producto::class
        );
    }

    public function usuario()
    {
        return $this->belongsTo(
            User::class
        );
    }
}
