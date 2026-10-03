<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Áreas que ven el expediente «para conocimiento» sin atenderlo (6: expediente_areas_copia).
        Schema::create('expediente_areas_copia', function (Blueprint $table) {
            $table->foreignId('expediente_id')->constrained('expedientes')->restrictOnDelete();
            $table->foreignId('area_id')->constrained('areas')->restrictOnDelete();
            $table->primary(['expediente_id', 'area_id']);
            $table->index('area_id');
        });

        // Nombres tal como estaban al derivar: el historial no cambia si luego se renombra el área.
        Schema::table('movimientos', function (Blueprint $table) {
            $table->jsonb('areas_copia')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('movimientos', fn (Blueprint $table) => $table->dropColumn('areas_copia'));
        Schema::dropIfExists('expediente_areas_copia');
    }
};
