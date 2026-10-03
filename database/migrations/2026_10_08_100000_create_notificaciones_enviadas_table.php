<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Avisos del sistema a personas (6: notificaciones_enviadas) y si llegaron: el Message-ID enlaza el rebote.
        Schema::create('notificaciones_enviadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('email', 200);
            $table->string('tipo', 30);
            $table->jsonb('expedientes')->default('[]');
            $table->string('estado', 15)->default('pendiente')->index();
            $table->string('message_id')->unique();
            $table->text('detalle')->nullable();
            $table->timestampTz('enviado_at')->nullable();
            $table->timestampTz('rebotado_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones_enviadas');
    }
};
