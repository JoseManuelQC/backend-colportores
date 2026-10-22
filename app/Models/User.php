<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'dni',
        'telefono',
        'role',
        'equipo_id',
        'campo',
        'is_active',
        'universidad_destino',
        'porcentaje_beca'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function ventas() {
        return $this->hasMany(Venta::class);
    }

    public function referencias() {
        return $this->hasMany(Referencia::class);
    }

    public function historiales() {
        return $this->hasMany(Historial::class);
    }

    public function inventario() {
        return $this->belongsToMany(Libro::class, 'inventario_colportores')
            ->withPivot('cantidad')
            ->withTimestamps();
    }

    public function equipo() {
        return $this->belongsTo(Equipo::class);
    }
}
