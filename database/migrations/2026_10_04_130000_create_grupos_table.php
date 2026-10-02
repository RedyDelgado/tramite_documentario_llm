<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Serie de documentos relacionados (7.3.1): se atienden como unidad sin perder cada registro.
        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->foreignId('creado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('expedientes', function (Blueprint $table) {
            $table->foreignId('grupo_id')->nullable()->after('custodio_id')->constrained('grupos')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expedientes', fn (Blueprint $table) => $table->dropConstrainedForeignId('grupo_id'));
        Schema::dropIfExists('grupos');
    }
};
