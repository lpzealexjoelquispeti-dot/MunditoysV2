<?php

namespace App\Models;

use Database\Factories\FotografiaModeladoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FotografiaModelado extends Model
{
    /** @use HasFactory<FotografiaModeladoFactory> */
    use HasFactory;

    protected $table = 'fotografias_modelado';

    protected $fillable = [
        'modelado_3d_id', 'ranura', 'version', 'nombre_original', 'ruta', 'miniatura', 'mascara', 'fraccion_mascara',
        'sha256', 'ancho', 'alto', 'bytes', 'estado', 'vista', 'motivos', 'metricas',
        'etiqueta_humana', 'revisado_por', 'revisado_en', 'reemplazada_en',
    ];

    protected $casts = [
        'ranura' => 'integer', 'version' => 'integer', 'ancho' => 'integer', 'alto' => 'integer',
        'bytes' => 'integer', 'fraccion_mascara' => 'float', 'motivos' => 'array', 'metricas' => 'array',
        'etiqueta_humana' => 'boolean', 'revisado_en' => 'datetime', 'reemplazada_en' => 'datetime',
    ];

    public function modelado(): BelongsTo
    {
        return $this->belongsTo(Modelado3D::class, 'modelado_3d_id');
    }

    public function getUrlAttribute(): string
    {
        return route('admin.fotogrametria.foto', ['modelado' => $this->modelado_3d_id, 'foto' => $this->id]);
    }
}
