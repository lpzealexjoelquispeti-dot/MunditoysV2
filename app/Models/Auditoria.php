<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auditoria extends Model
{
    use HasFactory;

    protected $table = 'auditorias';

    protected $fillable = [
        'usuario_id',
        'modulo_afectado',
        'accion',
        'tabla_afectada',
        'registro_id',
        'datos_anterior_jsonb',
        'datos_nuevo_jsonb',
        'ip',
        'user_agent',
    ];

    protected $casts = [
        'datos_anterior_jsonb' => 'array',
        'datos_nuevo_jsonb' => 'array',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
