<?php

namespace App\Models;

use Database\Factories\ValidacionFotogrametriaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValidacionFotogrametria extends Model
{
    /** @use HasFactory<ValidacionFotogrametriaFactory> */
    use HasFactory;

    protected $table = 'validaciones_fotogrametria';

    protected $fillable = ['modelado_3d_id', 'revision', 'version_algoritmo', 'parametros', 'resultado', 'duracion_ms'];

    protected $casts = ['revision' => 'integer', 'parametros' => 'array', 'resultado' => 'array', 'duracion_ms' => 'integer'];

    public function modelado(): BelongsTo
    {
        return $this->belongsTo(Modelado3D::class, 'modelado_3d_id');
    }
}
