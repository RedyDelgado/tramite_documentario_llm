<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reglas_derivacion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            // Condiciones: deben cumplirse todas las presentes (5.1). El tipo va en columna para tener FK.
            $table->foreignId('tipo_tramite_id')->nullable()->constrained('tipos_tramite')->restrictOnDelete();
            // {palabras_clave[], remitentes[]}
            $table->jsonb('condicion');
            $table->foreignId('area_destino_id')->constrained('areas')->restrictOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->restrictOnDelete();
            // Menor número se evalúa primero.
            $table->unsignedSmallInteger('prioridad')->default(100);
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index(['activa', 'prioridad']);
        });

        Schema::create('configuraciones', function (Blueprint $table) {
            $table->string('clave', 100)->primary();
            $table->jsonb('valor');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuraciones');
        Schema::dropIfExists('reglas_derivacion');
    }
};
