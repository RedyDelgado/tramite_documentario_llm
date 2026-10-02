<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cada clasificación de la IA y la decisión humana con la que se compara (9, 10). Solo se inserta y se completa la decisión.
        Schema::create('clasificaciones_ia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes')->restrictOnDelete();
            $table->string('modelo', 100);
            $table->string('version', 30);
            // Hash de la entrada: el texto no se duplica aquí (ya está en correos y documentos).
            $table->char('texto_sha256', 64);
            $table->jsonb('resultado');
            $table->foreignId('area_id')->nullable()->constrained('areas')->restrictOnDelete();
            $table->decimal('confianza_area', 5, 4)->nullable();
            $table->foreignId('tipo_tramite_id')->nullable()->constrained('tipos_tramite')->restrictOnDelete();
            $table->decimal('confianza_tipo', 5, 4)->nullable();
            $table->string('modo', 10);
            // Decisión humana (la primera derivación) y si la IA acertó.
            $table->foreignId('area_final_id')->nullable()->constrained('areas')->restrictOnDelete();
            $table->foreignId('tipo_final_id')->nullable()->constrained('tipos_tramite')->restrictOnDelete();
            $table->boolean('acierto_area')->nullable();
            $table->boolean('acierto_tipo')->nullable();
            $table->foreignId('decidido_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('decidido_at')->nullable();
            $table->timestampTz('created_at');

            $table->index(['expediente_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clasificaciones_ia');
    }
};
