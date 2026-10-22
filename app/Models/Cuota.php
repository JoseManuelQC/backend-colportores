<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cuota extends Model
{
    protected $guarded = [];

    // Relación inversa para saber a qué venta pertenece esta cuota
    public function venta() {
        return $this->belongsTo(Venta::class);
    }
}
