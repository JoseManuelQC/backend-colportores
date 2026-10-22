<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('referencias', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('equipo_id')->constrained('equipos')->onDelete('cascade');

            $table->enum('tipo', ['hogar_adventista', 'ayuda_misionera', 'iglesia_adventista']);
            $table->text('descripcion')->nullable();

            // Opcional: Guarda la ruta de la imagen si se tomó, si no, queda vacía (null)
            $table->string('foto_path')->nullable();

            $table->decimal('latitud', 10, 8);
            $table->decimal('longitud', 11, 8);

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('referencias');
    }
};
