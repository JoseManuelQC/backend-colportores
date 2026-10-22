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
    Schema::create('configuraciones', function (Blueprint $table) {
        $table->id();
        $table->foreignId('equipo_id')->constrained('equipos')->onDelete('cascade');
        $table->string('universidad')->unique(); // Lima, Juliaca, Tarapoto[cite: 2]
        $table->decimal('meta_media_beca', 10, 2)->default(0.00); 
        $table->decimal('meta_beca_completa', 10, 2)->default(0.00);
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
    }
};
