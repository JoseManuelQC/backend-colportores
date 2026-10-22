<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Libro extends Model
{
    protected $guarded = [];

    // --- NUEVO: Un libro pertenece a un equipo ---
    public function equipo()
    {
        return $this->belongsTo(Equipo::class);
    }

    public function colportores() {
        return $this->belongsToMany(User::class, 'inventario_colportores')
                    ->withPivot('cantidad')
                    ->withTimestamps();
    }
}