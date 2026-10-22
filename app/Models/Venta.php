<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    protected $guarded = [];

    // Relación con el Colportor
    public function user() {
        return $this->belongsTo(User::class);
    }

    // Relación con el Cliente
    public function cliente() {
        return $this->belongsTo(Cliente::class);
    }

    // Relación con los libros específicos que se vendieron
    public function detalles() {
        return $this->hasMany(VentaDetalle::class);
    }

    // Relación con el plan de pagos (Cuotas)
    public function cuotas() {
        return $this->hasMany(Cuota::class);
    }

    // Atributo calculado: Cuánto se ha pagado en total de las cuotas 'pagadas'
    public function getMontoPagadoAttribute() {
        return $this->cuotas()->where('estado', 'pagado')->sum('monto_pagado');
    }

    // Atributo calculado: Cuánto falta pagar
    public function getSaldoPendienteAttribute() {
        return $this->precio_final - $this->monto_pagado;
    }
}
