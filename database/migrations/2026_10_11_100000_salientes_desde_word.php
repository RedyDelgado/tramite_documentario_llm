<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los documentos se redactan en Word (decisión del usuario): el sistema ya no arma el texto con plantillas ni genera el
 * PDF. Se sube el borrador para revisarlo y, aprobado y numerado, el documento final con ese número, que es el que sale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos_salientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plantilla_id');
            // Ya no es el documento: es el mensaje opcional del correo que lo lleva adjunto.
            $table->text('cuerpo')->nullable()->change();
            $table->renameColumn('ruta_pdf', 'ruta_borrador');
            $table->renameColumn('sha256_pdf', 'sha256_borrador');
            // Nombres originales: dan la extensión (Word o PDF) al descargar y al adjuntar.
            $table->string('nombre_borrador')->nullable()->after('sha256_borrador');
            $table->string('nombre_firmado')->nullable()->after('sha256_firmado');
            // El número llega con la aprobación: siempre se espera el documento final que lo lleva.
            $table->boolean('esperar_firma')->default(true)->change();
        });

        Schema::dropIfExists('plantillas_documento');
    }

    public function down(): void
    {
        Schema::create('plantillas_documento', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150)->unique();
            $table->foreignId('tipo_documento_id')->constrained('tipos_documento')->restrictOnDelete();
            $table->string('asunto', 300);
            $table->text('cuerpo');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::table('documentos_salientes', function (Blueprint $table) {
            $table->foreignId('plantilla_id')->nullable()->after('expediente_id')->constrained('plantillas_documento')->restrictOnDelete();
            $table->dropColumn(['nombre_borrador', 'nombre_firmado']);
            $table->renameColumn('ruta_borrador', 'ruta_pdf');
            $table->renameColumn('sha256_borrador', 'sha256_pdf');
            $table->boolean('esperar_firma')->default(false)->change();
        });
    }
};
