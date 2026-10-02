<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Similitud de nombres para detectar emisores duplicados (7.3.5).
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        Schema::create('emisores', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            // Minúsculas, sin tildes ni puntuación: «Of. Gral.» y «of gral» son el mismo emisor.
            $table->string('nombre_normalizado', 200);
            $table->string('tipo', 10);
            $table->boolean('activo')->default(true);
            // Emisor duplicado que se fusionó en otro; queda para la trazabilidad.
            $table->foreignId('fusionado_en_id')->nullable()->constrained('emisores')->restrictOnDelete();
            $table->timestamps();
        });
        DB::statement('CREATE UNIQUE INDEX emisores_nombre_unico ON emisores (nombre_normalizado) WHERE fusionado_en_id IS NULL');

        Schema::create('tipos_documento', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100)->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('instrucciones_frecuentes', function (Blueprint $table) {
            $table->id();
            $table->string('texto', 200)->unique();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::table('expedientes', function (Blueprint $table) {
            $table->foreignId('emisor_id')->nullable()->after('remitente_por_confirmar')->constrained('emisores')->restrictOnDelete();
            $table->foreignId('tipo_documento_id')->nullable()->after('emisor_id')->constrained('tipos_documento')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tipo_documento_id');
            $table->dropConstrainedForeignId('emisor_id');
        });
        Schema::dropIfExists('instrucciones_frecuentes');
        Schema::dropIfExists('tipos_documento');
        Schema::dropIfExists('emisores');
    }
};
