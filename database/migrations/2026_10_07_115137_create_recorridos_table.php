<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('recorridos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('equipo_id')->constrained('equipos')->onDelete('cascade');

            $table->dateTime('fecha_inicio');
            $table->dateTime('fecha_fin')->nullable();

            // PostgreSQL maneja JSON perfectamente. Aquí irá el trazo de la línea: [{lat, lng}, {lat, lng}...]
            $table->json('coordenadas')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('recorridos');
    }
};
