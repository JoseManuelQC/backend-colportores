<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Pastor de la APSUR
        User::create([
            'name' => 'Pastor Samuel (APSUR)',
            'email' => 'samuel@apsur.com',
            'password' => Hash::make('password123'),
            'dni' => '12345678',       // CAMBIO: DNI obligatorio
            'telefono' => '987654321', // CAMBIO: Teléfono obligatorio
            'role' => 'pastor',
            'campo' => 'APSUR',
            'is_active' => true,
        ]);

        // 2. Pastor de la APN
        User::create([
            'name' => 'Pastor (APN)',
            'email' => 'admin@apn.com',
            'password' => Hash::make('password123'),
            'dni' => '87654321',       // CAMBIO: DNI obligatorio y único
            'telefono' => '912345678', // CAMBIO: Teléfono obligatorio
            'role' => 'pastor',
            'campo' => 'APN',
            'is_active' => true,
        ]);
    }
}
