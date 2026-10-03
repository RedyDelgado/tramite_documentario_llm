<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Primer número que emite el sistema en el año: lo anterior es del registro en papel (trámites en curso).
        Schema::table('secuencias', function (Blueprint $table) {
            $table->unsignedInteger('inicio')->nullable();
        });

        foreach (DB::table('secuencias')->get() as $fila) {
            DB::table('secuencias')->where('id', $fila->id)
                ->update(['inicio' => (int) (config("tramite.secuencias_inicio.{$fila->clave}.{$fila->anio}") ?? 1)]);
        }
    }

    public function down(): void
    {
        Schema::table('secuencias', fn (Blueprint $table) => $table->dropColumn('inicio'));
    }
};
