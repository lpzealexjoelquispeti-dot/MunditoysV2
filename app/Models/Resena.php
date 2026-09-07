<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resena extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'resenas';

    protected $fillable = [
        'producto_id',
        'user_id',
        'calificacion',
        'comentario',
        'estado',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }
}
