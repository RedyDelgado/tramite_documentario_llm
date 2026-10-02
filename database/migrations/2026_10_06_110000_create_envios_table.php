<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Un correo individual por destinatario (7.3.4): su estado de entrega y su Message-ID para enlazar respuestas y rebotes.
        Schema::create('envios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('documento_saliente_id')->constrained('documentos_salientes')->restrictOnDelete();
            $table->string('email', 200);
            $table->string('nombre', 200)->nullable();
            $table->string('estado', 15)->default('pendiente')->index();
            $table->string('message_id')->nullable()->unique();
            $table->string('proveedor_id', 100)->nullable();
            $table->timestampTz('enviado_at')->nullable();
            $table->timestampTz('rebotado_at')->nullable();
            $table->text('detalle')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios');
    }
};
