<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Historial extends Model
{
    protected $table = 'historial'; // Evita que Laravel busque "historials"
    protected $guarded = [];

    // Relación: Un historial pertenece a un usuario (contador o colportor)
    public function user() {
        return $this->belongsTo(User::class);
    }

    public function equipo()
    {
        return $this->belongsTo(Equipo::class);
    }
    
}
