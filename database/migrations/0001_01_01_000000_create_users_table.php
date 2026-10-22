<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');

            // --- NUEVOS CAMPOS OBLIGATORIOS ---
            $table->string('dni')->unique();
            $table->string('telefono');

            // --- MODIFICACIÓN: Roles Expandidos ---
            $table->enum('role', ['pastor', 'contador', 'lider', 'co-lider', 'colportor'])->default('colportor');

            // --- MODIFICACIÓN: Clave Foránea a Equipos ---
            $table->foreignId('equipo_id')->nullable()->constrained('equipos')->onDelete('set null');
            $table->string('campo')->nullable(); // Ej: APSUR, UPN

            $table->boolean('is_active')->default(true);

            $table->integer('libros_asignados')->default(0);
            $table->integer('libros_vendidos')->default(0);
            $table->integer('stock_actual')->default(0);
            $table->decimal('total_recaudado', 10, 2)->default(0.00);
            $table->decimal('ganancia_actual', 10, 2)->default(0.00);
            $table->decimal('deuda_actual', 10, 2)->default(0.00);

            $table->string('universidad_destino')->nullable();
            $table->decimal('porcentaje_beca', 5, 2)->default(0.00);

            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
