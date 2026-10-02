<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tipos_tramite', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150)->unique();
            $table->text('descripcion')->nullable();
            // Nulo: sin vencimiento, p. ej. comunicaciones para conocimiento (8).
            $table->unsignedSmallInteger('plazo_dias')->nullable();
            $table->string('tipo_dias', 10)->default('habiles');
            // Rol que aprueba el cierre (5); nulo: el cierre no requiere aprobación.
            $table->string('aprueba_cierre', 20)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('plazos_area', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tipo_tramite_id')->constrained('tipos_tramite')->restrictOnDelete();
            $table->foreignId('area_id')->constrained('areas')->restrictOnDelete();
            $table->unsignedSmallInteger('plazo_dias');
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement('CREATE UNIQUE INDEX plazos_area_unico ON plazos_area (tipo_tramite_id, area_id) WHERE deleted_at IS NULL');

        Schema::create('feriados', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('descripcion', 150);
            // Nulo: feriado de toda la institución; con área, solo cuenta para los plazos de esa área.
            $table->foreignId('area_id')->nullable()->constrained('areas')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::statement('CREATE UNIQUE INDEX feriados_unico ON feriados (fecha, area_id) NULLS NOT DISTINCT WHERE deleted_at IS NULL');

        Schema::table('expedientes', function (Blueprint $table) {
            $table->foreignId('tipo_tramite_id')->nullable()->after('responsable_id')->constrained('tipos_tramite')->restrictOnDelete();
            // Copia del plazo al asignar el tipo: cambiar la configuración no altera expedientes ya ingresados (5.1).
            $table->unsignedSmallInteger('plazo_dias_aplicado')->nullable()->after('tipo_tramite_id');
            $table->date('fecha_limite')->nullable()->after('plazo_dias_aplicado');
        });
    }

    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tipo_tramite_id');
            $table->dropColumn(['plazo_dias_aplicado', 'fecha_limite']);
        });
        Schema::dropIfExists('feriados');
        Schema::dropIfExists('plazos_area');
        Schema::dropIfExists('tipos_tramite');
    }
};
