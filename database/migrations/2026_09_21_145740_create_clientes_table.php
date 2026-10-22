<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            // Para que el líder pueda ver los clientes de su propio equipo
            $table->foreignId('equipo_id')->constrained('equipos')->onDelete('cascade');

            $table->string('nombre_completo');
            $table->string('dni')->nullable();
            $table->string('telefono')->nullable();
            $table->text('direccion')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('clientes');
    }
};
