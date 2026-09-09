<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'archivo_origen',
        'prompt_text',
        'modelo_glb_path',
        'progreso',
        'error_message',
    ];

    protected $casts = [
        'progreso' => 'integer',
        'producto_id' => 'integer',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);

        return $this->belongsTo(Producto::class)->withTrashed();
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
