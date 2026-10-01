<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            $table->timestampTz('fecha_hora', 6);
            // Sin FK a users: la auditoría no depende de que el usuario siga existiendo.
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('actor', 20);
            $table->string('accion', 80);
            $table->string('entidad', 80);
            $table->string('entidad_id', 64)->nullable();
            $table->jsonb('valor_anterior')->nullable();
            $table->jsonb('valor_nuevo')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->char('hash_anterior', 64)->nullable();
            $table->char('hash_registro', 64);

            $table->index(['entidad', 'entidad_id']);
            $table->index('fecha_hora');
        });

        // Inmutabilidad en la base, no solo en la aplicación (sección 9).
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION auditoria_inmutable() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'La tabla auditoria es de solo inserción';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER auditoria_sin_cambios BEFORE UPDATE OR DELETE ON auditoria
                FOR EACH ROW EXECUTE FUNCTION auditoria_inmutable();

            CREATE TRIGGER auditoria_sin_truncate BEFORE TRUNCATE ON auditoria
                FOR EACH STATEMENT EXECUTE FUNCTION auditoria_inmutable();
            SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
        DB::unprepared('DROP FUNCTION IF EXISTS auditoria_inmutable()');
    }
};
