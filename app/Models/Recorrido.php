<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Recorrido extends Model
{
    protected $guarded = [];

    protected $casts = [
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
        'coordenadas' => 'array', // Transforma el JSON de PostgreSQL a un Arreglo de PHP automáticamente
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }
}
