<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Modelado3D extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'modelados_3d';

    protected $fillable = [
        'producto_id',
        'task_id',
        'status',
        'tipo_generacion',
        'modo_captura',
        'archivo_origen',
        'prompt_text',
        'modelo_glb_path',
        'progreso',
        'error_message',
        'referencia_foto_id',
        'revision',
        'revision_analizada',
        'resultado_validacion',
        'analizado_en',
        'validacion_token',
        'sesion_origen_id',
    ];

    protected $casts = [
        'progreso' => 'integer',
        'producto_id' => 'integer',
        'referencia_foto_id' => 'integer',
        'revision' => 'integer',
        'revision_analizada' => 'integer',
        'resultado_validacion' => 'array',
        'analizado_en' => 'datetime',
        'sesion_origen_id' => 'integer',
    ];

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class)->withTrashed();
    }

    public function fotografias(): HasMany
    {
        return $this->hasMany(FotografiaModelado::class, 'modelado_3d_id')
            ->whereNull('reemplazada_en')->orderBy('ranura');
    }

    public function validaciones(): HasMany
    {
        return $this->hasMany(ValidacionFotogrametria::class, 'modelado_3d_id');
    }

    public function reconstrucciones(): HasMany
    {
        return $this->hasMany(ReconstruccionFotogrametria::class, 'modelado_3d_id');
    }

    public function sesionOrigen(): BelongsTo
    {
        return $this->belongsTo(self::class, 'sesion_origen_id');
    }

    public function getUrlGlbAttribute(): ?string
    {
        if (empty($this->modelo_glb_path)) {
            return null;
        }

        if (str_starts_with($this->modelo_glb_path, 'http://') || str_starts_with($this->modelo_glb_path, 'https://')) {
            return $this->modelo_glb_path;
        }

        if (file_exists(public_path($this->modelo_glb_path))) {
            return asset($this->modelo_glb_path);
        }

        return asset('storage/'.$this->modelo_glb_path);
    }

    public function getEsCompletadoAttribute(): bool
    {
        return in_array($this->status, ['success', 'completed'], true);
    }

    public function getEsFallidoAttribute(): bool
    {
        return $this->status === 'failed';
    }

    public function getEnProcesoAttribute(): bool
    {
        return ! $this->es_completado && ! $this->es_fallido;
    }
}
