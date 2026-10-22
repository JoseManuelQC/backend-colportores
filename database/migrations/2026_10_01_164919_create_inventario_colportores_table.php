<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_colportores', function (Blueprint $table) {
            $table->id();
            
            // Relación con el colportor y el libro
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('libro_id')->constrained('libros')->onDelete('cascade');
            
            // Cuántos ejemplares de este libro específico tiene en su maleta
            $table->integer('cantidad')->default(0); 
            
            $table->timestamps();

            // Evitar que un mismo libro se asigne dos veces como filas separadas al mismo colportor
            $table->unique(['user_id', 'libro_id']); 
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_colportores');
    }
};