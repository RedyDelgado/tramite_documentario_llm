<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes')->restrictOnDelete();
            // Idempotencia (7.1): un Message-ID entra una sola vez.
            $table->string('message_id', 512)->unique();
            $table->jsonb('en_respuesta_a')->default('[]');
            $table->string('uid_externo')->nullable();
            $table->string('de_email');
            $table->string('de_nombre')->nullable();
            $table->jsonb('para')->default('[]');
            $table->jsonb('cc')->default('[]');
            $table->text('asunto');
            $table->timestampTz('fecha');
            $table->text('cuerpo_texto')->nullable();
            $table->boolean('es_reenvio')->default(false);
            $table->string('ruta_eml');
            $table->char('sha256', 64);
            $table->timestamps();
        });

        Schema::create('documentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes')->restrictOnDelete();
            $table->foreignId('correo_id')->nullable()->constrained('correos')->restrictOnDelete();
            // Una corrección es una versión nueva, nunca una sobrescritura (2).
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('nombre_original');
            $table->string('ruta');
            $table->string('mime', 150);
            $table->unsignedBigInteger('tamano');
            $table->char('sha256', 64)->index();
            $table->text('texto_extraido')->nullable();
            $table->boolean('es_adjunto')->default(true);
            $table->timestamps();
        });

        // Reglas editables desde el panel en la fase 2; la IA solo sugerirá (7.3.2).
        Schema::create('reglas_no_tramite', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('campo', 20);
            $table->string('valor');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglas_no_tramite');
        Schema::dropIfExists('documentos');
        Schema::dropIfExists('correos');
    }
};
