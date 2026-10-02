<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Archivador, caja o estante donde se guarda el original en papel (7.3.1).
        Schema::create('ubicaciones_fisicas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150)->unique();
            $table->string('descripcion', 300)->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::table('expedientes', function (Blueprint $table) {
            $table->foreignId('ubicacion_fisica_id')->nullable()->after('motivo_folios')->constrained('ubicaciones_fisicas')->restrictOnDelete();
            $table->foreignId('custodio_id')->nullable()->after('ubicacion_fisica_id')->constrained('users')->restrictOnDelete();
        });

        // El cargo de entrega firmado se escanea y se adjunta a su movimiento (7.3.1).
        Schema::table('documentos', function (Blueprint $table) {
            $table->foreignId('movimiento_id')->nullable()->after('correo_id')->constrained('movimientos')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documentos', fn (Blueprint $table) => $table->dropConstrainedForeignId('movimiento_id'));
        Schema::table('expedientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('custodio_id');
            $table->dropConstrainedForeignId('ubicacion_fisica_id');
        });
        Schema::dropIfExists('ubicaciones_fisicas');
    }
};
