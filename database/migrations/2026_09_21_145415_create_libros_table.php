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
    Schema::create('libros', function (Blueprint $table) {
        $table->id();
        $table->foreignId('equipo_id')->constrained('equipos')->onDelete('cascade');
        $table->string('titulo'); // Nombre del libro o revista
        $table->string('imagen_referencial')->nullable(); // Ruta de la foto
        
        // Precios del catálogo
        $table->decimal('precio_costo', 8, 2); // Precio al que se le deja al colportor
        $table->decimal('precio_venta', 8, 2); // Precio sugerido al público
        
        // Control de Inventario Global
        $table->integer('total_recibidos')->default(0); // Libros que llegaron para la campaña
        $table->integer('total_entregados')->default(0); // Repartidos a los colportores
        $table->integer('stock_reserva')->default(0); // Lo que queda en almacén
        
        $table->boolean('is_active')->default(true); // Para ocultar libros agotados de la app
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('libros');
    }
};
