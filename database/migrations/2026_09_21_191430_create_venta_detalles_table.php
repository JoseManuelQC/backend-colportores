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
    Schema::create('venta_detalles', function (Blueprint $table) {
        $table->id();
        $table->foreignId('venta_id')->constrained('ventas')->onDelete('cascade');
        $table->foreignId('libro_id')->constrained('libros')->onDelete('cascade');
        $table->integer('cantidad'); // Ej: 2 (libros de jugos)
        $table->decimal('precio_unitario', 8, 2); // Precio al que se le vendió
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('venta_detalles');
    }
};
