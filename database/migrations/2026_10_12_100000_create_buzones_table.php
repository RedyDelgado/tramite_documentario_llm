<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Varias cuentas de Google en un mismo buzón central (decisión del usuario); una es la principal, la que envía.
 * Lo ya leído se recuerda en la base (correos_leidos) y no con la etiqueta de Gmail: vaciar la base o agregar una
 * cuenta no deja correos sin leer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buzones', function (Blueprint $table) {
            $table->id();
            $table->string('cuenta')->unique();
            // Cifrado con APP_KEY (cast encrypted); nunca va a la auditoría.
            $table->text('refresh_token');
            $table->boolean('principal')->default(false);
            $table->jsonb('ultima_lectura')->nullable();
            $table->timestamps();
        });

        Schema::create('correos_leidos', function (Blueprint $table) {
            $table->id();
            // Cuenta de Google o «directorio» (buzón de prueba); uid es el id del mensaje en ese buzón.
            $table->string('buzon');
            $table->string('uid');
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['buzon', 'uid']);
        });

        // La cuenta conectada antes desde el panel pasa a ser la principal.
        $token = DB::table('configuraciones')->where('clave', 'correo.gmail.refresh_token')->value('valor');
        $cuenta = DB::table('configuraciones')->where('clave', 'correo.gmail.cuenta')->value('valor');
        if ($token && $cuenta) {
            DB::table('buzones')->insert([
                'cuenta' => json_decode($cuenta, true) ?? $cuenta,
                'refresh_token' => json_decode($token, true) ?? $token,
                'principal' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('configuraciones')->whereIn('clave', ['correo.gmail.refresh_token', 'correo.gmail.cuenta'])->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('correos_leidos');
        Schema::dropIfExists('buzones');
    }
};
