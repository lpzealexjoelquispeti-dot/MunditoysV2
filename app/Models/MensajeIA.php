<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MensajeIA extends Model
{
    use HasFactory;

    protected $table = 'mensajes_ia';

    protected $fillable = [
        'conversacion_id',
        'rol',
        'mensaje',
        'modelo',
        'tokens',
    ];

    public function conversacion()
    {
        return $this->belongsTo(
            ConversacionIA::class,
            'conversacion_id'
        );
    }
}
