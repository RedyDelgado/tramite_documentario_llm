<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expedientes', function (Blueprint $table) {
            // Normalizado (7.3.5, punto 8); el texto tal como venía se guarda aparte.
            $table->string('numero_documento', 150)->nullable()->after('tipo_documento_id');
            $table->string('numero_documento_original', 200)->nullable()->after('numero_documento');
            $table->date('fecha_documento')->nullable()->after('numero_documento_original');
            $table->unsignedSmallInteger('folios')->nullable()->after('fecha_documento');
            // Folios distintos de las páginas del escaneo solo con motivo (7.3.5, punto 1).
            $table->string('motivo_folios', 300)->nullable()->after('folios');
        });

        // Clave de unicidad de negocio (7.3.5): un mismo documento no se registra dos veces; los anulados no cuentan.
        DB::statement("CREATE UNIQUE INDEX expedientes_documento_unico ON expedientes
            (emisor_id, tipo_documento_id, numero_documento, (EXTRACT(YEAR FROM fecha_documento)))
            WHERE estado <> 'anulado' AND numero_documento IS NOT NULL");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS expedientes_documento_unico');
        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropColumn(['numero_documento', 'numero_documento_original', 'fecha_documento', 'folios', 'motivo_folios']);
        });
    }
};
