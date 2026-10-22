<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Colportor
            $table->foreignId('cliente_id')->constrained('clientes')->onDelete('cascade'); // Cliente

            $table->decimal('latitud', 10, 8)->nullable();
            $table->decimal('longitud', 11, 8)->nullable();

            $table->decimal('precio_final', 8, 2)->default(0);

            // Lógica de estado de la venta
            $table->enum('tipo_pago', ['contado', 'cuotas'])->default('contado');
            $table->enum('estado_pago', ['pendiente', 'pagado', 'anulado'])->default('pendiente');
            // NUEVO: Para saber si el pin se muestra a todo el equipo
            $table->boolean('es_publica')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('ventas');
    }
};
