<?php

namespace App\Models;

use Database\Factories\ReconstruccionFotogrametriaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReconstruccionFotogrametria extends Model
{
    /** @use HasFactory<ReconstruccionFotogrametriaFactory> */
    use HasFactory;

    protected $table = 'reconstrucciones_fotogrametria';

    protected $fillable = [
        'modelado_3d_id', 'revision', 'estado', 'etapa', 'progreso', 'token', 'mensaje_error',
        'parametros', 'resultado', 'modelo_generado_id', 'duracion_ms', 'iniciado_en', 'finalizado_en',
    ];

    protected $casts = [
        'revision' => 'integer', 'progreso' => 'integer', 'parametros' => 'array', 'resultado' => 'array',
        'duracion_ms' => 'integer', 'iniciado_en' => 'datetime', 'finalizado_en' => 'datetime',
    ];

    public function sesion(): BelongsTo
    {
        return $this->belongsTo(Modelado3D::class, 'modelado_3d_id');
    }

    public function modeloGenerado(): BelongsTo
    {
        return $this->belongsTo(Modelado3D::class, 'modelo_generado_id');
    }

    /**
     * En cola o procesando, y con actividad reciente dentro del tiempo máximo configurado.
     */
    public function getEstaActivaAttribute(): bool
    {
        return in_array($this->estado, ['en_cola', 'procesando'], true)
            && $this->updated_at?->gt(now()->subSeconds((int) config('fotogrametria.reconstruccion.timeout') + 120));
    }
}
