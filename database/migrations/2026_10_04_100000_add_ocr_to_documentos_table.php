<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->unsignedSmallInteger('paginas')->nullable()->after('tamano');
            // El texto vino del OCR (escaneo): puede tener errores de reconocimiento.
            $table->boolean('texto_por_ocr')->default(false)->after('texto_extraido');
        });
    }

    public function down(): void
    {
        Schema::table('documentos', function (Blueprint $table) {
            $table->dropColumn(['paginas', 'texto_por_ocr']);
        });
    }
};
