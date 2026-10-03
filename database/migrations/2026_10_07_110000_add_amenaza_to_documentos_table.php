<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Firma que encontró el antivirus (11); con ella el archivo queda en cuarentena: sin texto, OCR ni descarga.
        Schema::table('documentos', function (Blueprint $table) {
            $table->string('amenaza', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('documentos', fn (Blueprint $table) => $table->dropColumn('amenaza'));
    }
};
