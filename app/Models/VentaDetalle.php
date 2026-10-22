<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VentaDetalle extends Model
{
    protected $guarded = [];

    public function venta() {
        return $this->belongsTo(Venta::class);
    }

    public function libro() {
        return $this->belongsTo(Libro::class);
    }
}