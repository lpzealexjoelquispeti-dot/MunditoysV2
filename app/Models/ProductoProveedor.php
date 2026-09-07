<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductoProveedor extends Model
{
    use HasFactory;

    protected $table = 'producto_proveedor';

    protected $fillable = [
        'producto_id',
        'proveedor_id',
        'codigo_proveedor',
        'costo_compra',
        'tiempo_entrega_dias',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }
}
