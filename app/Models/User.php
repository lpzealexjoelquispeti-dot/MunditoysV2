<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, SoftDeletes;

    protected $fillable = [
        'name',
        'apellido_paterno',
        'apellido_materno',
        'email',
        'telefono',
        'ci_nit',
        'password',
        'tipo_usuario',
        'foto',
        'estado',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'invitacion_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'estado' => 'boolean',
            'invitacion_pendiente' => 'boolean',
            'invitacion_expira_at' => 'datetime',
            'invitacion_enviada_at' => 'datetime',
            'invitacion_revocada_at' => 'datetime',
            'sesion_version' => 'integer',
        ];
    }

    public function direcciones()
    {
        return $this->hasMany(Direccion::class);
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class, 'cliente_id');
    }

    public function reseñas()
    {
        return $this->hasMany(Resena::class);
    }

    public function auditorias()
    {
        return $this->hasMany(Auditoria::class);
    }

    public function conversacionesIA()
    {
        return $this->hasMany(ConversacionIA::class);
    }

    public function horarios()
    {
        return $this->hasMany(
            HorarioEmpleado::class,
            'usuario_id'
        );
    }

    public function disponibilidades()
    {
        return $this->hasMany(
            DisponibilidadEmpleado::class,
            'usuario_id'
        );
    }

    public function entregasAsignadas()
    {
        return $this->hasMany(
            Entrega::class,
            'empleado_id'
        );
    }

    public function historialPrecios()
    {
        return $this->hasMany(
            HistorialPrecio::class
        );
    }

    public function notificaciones()
    {
        return $this->hasMany(
            Notificacion::class
        );
    }
}
