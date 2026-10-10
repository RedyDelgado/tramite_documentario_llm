<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Áreas que atienden un expediente (decisión del usuario): cada una con su responsable, plazo y estado.
        // El expediente queda atendido cuando todas lo atienden; sus columnas area_principal_id, responsable_id
        // y fecha_limite pasan a ser un resumen que mantiene AtencionService.
        Schema::create('expediente_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes')->restrictOnDelete();
            $table->foreignId('area_id')->constrained('areas')->restrictOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedSmallInteger('plazo_dias_aplicado')->nullable();
            $table->date('fecha_limite')->nullable();
            $table->string('estado', 20)->default('derivado');
            $table->timestampTz('atendido_at')->nullable();
            $table->foreignId('atendido_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('nota_atencion')->nullable();
            $table->timestampsTz();
            $table->unique(['expediente_id', 'area_id']);
            $table->index('area_id');
            $table->index('responsable_id');
        });

        // Nombres de las áreas tal como estaban al derivar, como areas_copia.
        Schema::table('movimientos', fn (Blueprint $table) => $table->jsonb('areas')->nullable());

        DB::statement(<<<'SQL'
            INSERT INTO expediente_areas (expediente_id, area_id, responsable_id, plazo_dias_aplicado, fecha_limite, estado, atendido_at, created_at, updated_at)
            SELECT id, area_principal_id, responsable_id, plazo_dias_aplicado, fecha_limite,
                   CASE estado WHEN 'en_atencion' THEN 'en_atencion' WHEN 'derivado' THEN 'derivado' WHEN 'registrado' THEN 'derivado' ELSE 'atendido' END,
                   CASE WHEN estado IN ('derivado', 'registrado', 'en_atencion') THEN NULL ELSE atendido_at END,
                   now(), now()
            FROM expedientes WHERE area_principal_id IS NOT NULL
            SQL);
    }

    public function down(): void
    {
        Schema::table('movimientos', fn (Blueprint $table) => $table->dropColumn('areas'));
        Schema::dropIfExists('expediente_areas');
    }
};
