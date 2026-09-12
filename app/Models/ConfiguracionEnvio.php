<?php

namespace App\Models;

use Database\Factories\ConfiguracionEnvioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionEnvio extends Model
{
    /** @use HasFactory<ConfiguracionEnvioFactory> */
    use HasFactory;

    protected $table = 'configuracion_envios';

    protected $fillable = ['habilitado', 'tarifa_km_usd', 'minimo_usd', 'cambio_paralelo'];

    protected $casts = [
        'habilitado' => 'boolean',
        'tarifa_km_usd' => 'decimal:4',
        'minimo_usd' => 'decimal:4',
        'cambio_paralelo' => 'decimal:4',
    ];
}
