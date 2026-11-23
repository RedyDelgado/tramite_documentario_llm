<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chat entre usuarios: de persona a persona (con `clave` única para no duplicarla) o en grupo (con nombre).
        Schema::create('conversaciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 80)->nullable();
            $table->string('clave', 40)->nullable()->unique();
            $table->foreignId('creada_por')->constrained('users')->restrictOnDelete();
            $table->timestampTz('ultimo_mensaje_at')->nullable()->index();
            $table->timestampsTz();
        });

        // `leido_hasta` es el id del último mensaje visto: lo posterior de otros cuenta como no leído.
        Schema::create('conversacion_usuario', function (Blueprint $table) {
            $table->foreignId('conversacion_id')->constrained('conversaciones')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('leido_hasta')->nullable();
            $table->timestampsTz();
            $table->primary(['conversacion_id', 'user_id']);
            $table->index('user_id');
        });

        // Nada se borra físicamente (2): no hay columna para editar ni eliminar mensajes.
        Schema::create('mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversacion_id')->constrained('conversaciones')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->text('texto');
            $table->timestampsTz();
            $table->index(['conversacion_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mensajes');
        Schema::dropIfExists('conversacion_usuario');
        Schema::dropIfExists('conversaciones');
    }
};
