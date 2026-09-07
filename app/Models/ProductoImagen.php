<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductoImagen extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'producto_imagenes';

    protected $fillable = [
        'producto_id',
        'ruta_archivo',
        'tipo_imagen',
        'orden',
        'principal',
    ];

    protected $casts = [
        'principal' => 'boolean',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
