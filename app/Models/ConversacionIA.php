<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConversacionIA extends Model
{
    use HasFactory;

    protected $table = 'conversaciones_ia';

    protected $fillable = [
        'usuario_id',
        'titulo',
        'metadata_jsonb',
    ];

    protected $casts = [
        'metadata_jsonb' => 'array',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }

    public function mensajes()
    {
        return $this->hasMany(
            MensajeIA::class,
            'conversacion_id'
        );
    }
}
