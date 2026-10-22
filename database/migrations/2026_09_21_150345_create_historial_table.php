<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('historial', function (Blueprint $table) {
        $table->id();
        $table->foreignId('equipo_id')->constrained('equipos')->onDelete('cascade');
        $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Contador que hizo el registro
        $table->string('accion'); // Ej: 'Entrega de material', 'Depósito recibido'
        $table->text('detalles'); // Explicación de la acción
        $table->timestamps(); // Genera automáticamente la Fecha/Hora del registro[cite: 2]
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial');
    }
};
