<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Historial estructurado de la atención (6, 6.1): derivaciones, cambios de estado y comentarios. Solo se inserta.
        Schema::create('movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes')->restrictOnDelete();
            $table->string('tipo', 30);
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('de_area_id')->nullable()->constrained('areas')->restrictOnDelete();
            $table->foreignId('a_area_id')->nullable()->constrained('areas')->restrictOnDelete();
            $table->foreignId('a_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('instruccion', 200)->nullable();
            $table->text('nota')->nullable();
            // Plazo de la derivación como fecha, no como texto (6.1).
            $table->date('fecha_limite')->nullable();
            $table->timestampTz('created_at');

            $table->index(['expediente_id', 'id']);
        });

        Schema::table('expedientes', function (Blueprint $table) {
            $table->boolean('requiere_respuesta')->default(true)->after('tipo_documento_id');
            // Cacheado: lo recalcula SemaforoService en cada movimiento y cada día (8).
            $table->string('semaforo', 10)->nullable()->index()->after('estado');
            $table->timestampTz('ultimo_movimiento_at')->nullable()->after('fecha_limite');
            $table->timestampTz('cierre_solicitado_at')->nullable()->after('ultimo_movimiento_at');
            $table->timestampTz('atendido_at')->nullable()->after('cierre_solicitado_at');
        });

        // Lo ya ingresado y aún sin derivar está pendiente de clasificar (SemaforoService).
        DB::table('expedientes')->whereIn('estado', ['por_revisar', 'registrado'])->update(['semaforo' => 'gris']);
    }

    public function down(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropColumn(['requiere_respuesta', 'semaforo', 'ultimo_movimiento_at', 'cierre_solicitado_at', 'atendido_at']);
        });
        Schema::dropIfExists('movimientos');
    }
};
