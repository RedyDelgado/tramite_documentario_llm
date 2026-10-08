<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quién emite y a qué institución pertenece (decisión del usuario): el director del hospital es una persona del
 * Hospital de Quillabamba; la municipalidad emite como institución; un ciudadano no pertenece a ninguna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emisores', function (Blueprint $table) {
            $table->string('clase', 15)->default('persona')->after('tipo');
            // Institución habitual de una persona: se propone al registrar; cada expediente guarda la suya.
            $table->foreignId('institucion_id')->nullable()->after('clase')->constrained('emisores')->nullOnDelete();
        });
        // Lo cargado hasta hoy son dependencias e instituciones.
        DB::table('emisores')->update(['clase' => 'institucion']);

        Schema::table('expedientes', function (Blueprint $table) {
            // La institución en ese documento: si la persona cambia de trabajo, lo anterior conserva la suya.
            $table->foreignId('institucion_id')->nullable()->after('emisor_id')->constrained('emisores')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('expedientes', fn (Blueprint $table) => $table->dropConstrainedForeignId('institucion_id'));
        Schema::table('emisores', function (Blueprint $table) {
            $table->dropConstrainedForeignId('institucion_id');
            $table->dropColumn('clase');
        });
    }
};
