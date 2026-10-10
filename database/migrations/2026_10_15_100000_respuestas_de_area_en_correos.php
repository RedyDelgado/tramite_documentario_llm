<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un correo que llega a un expediente con áreas pendientes es una posible respuesta: la IA propone si responde
        // a lo pedido y una persona del área confirma (decisión del usuario).
        Schema::table('correos', function (Blueprint $table) {
            $table->boolean('respuesta_area')->default(false);
            $table->string('ia_respuesta', 20)->nullable();
            $table->text('ia_motivo')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('correos', fn (Blueprint $table) => $table->dropColumn(['respuesta_area', 'ia_respuesta', 'ia_motivo']));
    }
};
