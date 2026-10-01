<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Correlativos sin saltos: una fila por clave y año, incrementada con bloqueo de fila (6.2).
        Schema::create('secuencias', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 60);
            $table->smallInteger('anio');
            $table->unsignedInteger('ultimo');
            $table->timestamps();

            $table->unique(['clave', 'anio']);
        });

        Schema::create('area_responsables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained('areas')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('tipo', 10);
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'vigente_desde']);
        });

        Schema::create('expedientes', function (Blueprint $table) {
            $table->id();
            // Clave de negocio (6.2): nula hasta confirmarse como trámite; única por año.
            $table->smallInteger('anio')->nullable();
            $table->unsignedInteger('secuencia')->nullable();
            $table->string('origen', 20);
            $table->string('estado', 20)->index();
            $table->string('asunto', 500);
            $table->string('remitente_nombre')->nullable();
            $table->string('remitente_email')->nullable();
            // Reenvío del que no se pudo extraer el remitente original (7.1).
            $table->boolean('remitente_por_confirmar')->default(false);
            $table->timestampTz('fecha_ingreso');
            $table->timestampTz('registrado_at')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('area_principal_id')->nullable()->constrained('areas')->restrictOnDelete();
            $table->foreignId('responsable_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('motivo_anulacion')->nullable();
            $table->timestamps();

            $table->unique(['anio', 'secuencia']);
            $table->index('fecha_ingreso');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expedientes');
        Schema::dropIfExists('area_responsables');
        Schema::dropIfExists('secuencias');
    }
};
