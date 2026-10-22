<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referencia extends Model
{
    protected $guarded = [];

    // Para saber qué colportor la registró
    public function user() {
        return $this->belongsTo(User::class);
    }

    // Para que el líder pueda filtrar las referencias de su equipo
    public function equipo() {
        return $this->belongsTo(Equipo::class);
    }
}
