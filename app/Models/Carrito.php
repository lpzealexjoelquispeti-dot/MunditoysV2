<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Carrito extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'usuario_id',
        'estado',
        'tipo_compra',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class);

        return $this->belongsTo(User::class, 'user_id');
    }

    public function detalles()
    {
        return $this->hasMany(CarritoDetalle::class);
    }
}
