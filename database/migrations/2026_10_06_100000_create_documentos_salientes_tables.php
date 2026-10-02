<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Siglas del área para la numeración de lo que emite, p. ej. OFICIO N.º 012-2026-DGA (7.3.4).
        Schema::table('areas', function (Blueprint $table) {
            $table->string('siglas', 20)->nullable()->after('nombre');
        });

        Schema::table('tipos_documento', function (Blueprint $table) {
            $table->string('formato_numero', 100)->default('{TIPO} N.º {NUMERO}-{ANIO}-{AREA}')->after('nombre');
            // Rol que aprueba la salida (5): director o coordinador del área que emite.
            $table->string('aprueba_salida', 20)->default('director')->after('formato_numero');
        });

        Schema::create('plantillas_documento', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150)->unique();
            $table->foreignId('tipo_documento_id')->constrained('tipos_documento')->restrictOnDelete();
            $table->string('asunto', 300);
            $table->text('cuerpo');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('documentos_salientes', function (Blueprint $table) {
            $table->id();
            // El expediente que origina el documento; su código va en el asunto del envío (7.2).
            $table->foreignId('expediente_id')->nullable()->constrained('expedientes')->restrictOnDelete();
            $table->foreignId('plantilla_id')->nullable()->constrained('plantillas_documento')->restrictOnDelete();
            $table->foreignId('tipo_documento_id')->constrained('tipos_documento')->restrictOnDelete();
            $table->foreignId('area_id')->constrained('areas')->restrictOnDelete();
            // Numeración por tipo, área y año; nula hasta la aprobación.
            $table->smallInteger('anio')->nullable();
            $table->unsignedInteger('secuencia')->nullable();
            $table->string('numero', 150)->nullable();
            $table->string('asunto', 300);
            $table->text('cuerpo');
            $table->jsonb('destinatarios');
            // Respuesta al expediente: al enviarse lo deja atendido (7.3.5, punto 7).
            $table->boolean('es_respuesta')->default(false);
            $table->boolean('requiere_respuesta')->default(false);
            $table->unsignedSmallInteger('plazo_respuesta_dias')->nullable();
            $table->date('fecha_limite_respuesta')->nullable();
            // Esperar el PDF firmado fuera del sistema antes de enviar (7.3.4).
            $table->boolean('esperar_firma')->default(false);
            $table->string('estado', 20)->index();
            $table->text('observacion')->nullable();
            $table->foreignId('creado_por')->constrained('users')->restrictOnDelete();
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('aprobado_at')->nullable();
            $table->string('ruta_pdf')->nullable();
            $table->char('sha256_pdf', 64)->nullable();
            $table->string('ruta_firmado')->nullable();
            $table->char('sha256_firmado', 64)->nullable();
            $table->timestampTz('enviado_at')->nullable();
            $table->timestampTz('respondido_at')->nullable();
            $table->timestamps();

            $table->unique(['tipo_documento_id', 'area_id', 'anio', 'secuencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_salientes');
        Schema::dropIfExists('plantillas_documento');
        Schema::table('tipos_documento', fn (Blueprint $table) => $table->dropColumn(['formato_numero', 'aprueba_salida']));
        Schema::table('areas', fn (Blueprint $table) => $table->dropColumn('siglas'));
    }
};
