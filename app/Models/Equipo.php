<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipo extends Model
{
    protected $guarded = [];

    // Un equipo tiene muchos usuarios (lideres, financieros, colportores)
    public function usuarios() {
        return $this->hasMany(User::class);
    }
}