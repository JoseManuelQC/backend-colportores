<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $guarded = [];

    // Relación para saber a qué equipo pertenece este cliente
    public function equipo() {
        return $this->belongsTo(Equipo::class);
    }

    // Un cliente puede tener muchas ventas (compras) a lo largo del tiempo
    public function ventas() {
        return $this->hasMany(Venta::class);
    }
}
