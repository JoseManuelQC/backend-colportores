<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('cuotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->onDelete('cascade');

            // Planificación de la cuota
            $table->integer('numero_cuota'); // 1 (inicial), 2, 3...
            $table->decimal('monto_esperado', 8, 2);
            $table->date('fecha_vencimiento');
            $table->enum('estado', ['pendiente', 'pagado'])->default('pendiente');

            // Ejecución del cobro (Se llena cuando el cliente realmente paga)
            $table->decimal('monto_pagado', 8, 2)->nullable();
            $table->date('fecha_pago_real')->nullable();
            $table->enum('metodo_pago', ['efectivo', 'yape', 'plin', 'transferencia', 'otro'])->nullable();

            // Geolocalización del momento exacto del cobro
            $table->decimal('latitud', 10, 8)->nullable();
            $table->decimal('longitud', 11, 8)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('cuotas');
    }
};
