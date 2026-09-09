<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InteraccionUsuario extends Model
{
    use HasFactory;

    protected $table = 'interacciones_usuario';

    protected $fillable = [
        'user_id',
        'usuario_id',
        'producto_id',
        'tipo_interaccion',
        'datos_jsonb',
    ];

    protected $casts = [
        'datos_jsonb' => 'array',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function producto()
    {
        return $this->belongsTo(
            Producto::class
        );
    }
}
