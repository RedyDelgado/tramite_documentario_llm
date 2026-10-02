<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cuando la decisión humana contradice a la IA (10): solo las validadas alimentan el reentrenamiento.
        Schema::create('correcciones_pendientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clasificacion_id')->constrained('clasificaciones_ia')->restrictOnDelete();
            $table->string('campo', 10);
            $table->unsignedBigInteger('valor_ia')->nullable();
            $table->unsignedBigInteger('valor_humano');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('estado', 10)->default('pendiente')->index();
            $table->foreignId('validada_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('validada_at')->nullable();
            $table->timestamps();

            $table->unique(['clasificacion_id', 'campo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('correcciones_pendientes');
    }
};
