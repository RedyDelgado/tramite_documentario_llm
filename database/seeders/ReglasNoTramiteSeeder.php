<?php

namespace Database\Seeders;

use App\Models\ReglaNoTramite;
use Illuminate\Database\Seeder;

/** Reglas iniciales de correo masivo o automático (7.3.2); idempotente. */
class ReglasNoTramiteSeeder extends Seeder
{
    public function run(): void
    {
        $reglas = [
            ['nombre' => 'Boletines con enlace de baja', 'campo' => 'encabezado', 'valor' => 'list-unsubscribe'],
            ['nombre' => 'Respuestas automáticas', 'campo' => 'encabezado', 'valor' => 'auto-submitted: auto-'],
            ['nombre' => 'Envíos masivos', 'campo' => 'encabezado', 'valor' => 'precedence: bulk'],
            ['nombre' => 'Listas de correo', 'campo' => 'encabezado', 'valor' => 'precedence: list'],
            ['nombre' => 'Remitentes noreply', 'campo' => 'remitente', 'valor' => 'noreply'],
            ['nombre' => 'Remitentes no-reply', 'campo' => 'remitente', 'valor' => 'no-reply'],
            ['nombre' => 'Rebotes de correo', 'campo' => 'remitente', 'valor' => 'mailer-daemon'],
        ];

        foreach ($reglas as $regla) {
            ReglaNoTramite::firstOrCreate(['campo' => $regla['campo'], 'valor' => $regla['valor']], $regla);
        }
    }
}
