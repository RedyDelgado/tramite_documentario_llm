<?php

namespace App\Console\Commands;

use App\Correo\MailboxDriver;
use App\Services\IngestaCorreoService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('correo:importar {--limite= : Mensajes a procesar (por defecto CORREO_LOTE)}')]
#[Description('Procesa ahora los correos pendientes del buzón central (mismo proceso que el job programado)')]
class ImportarCorreos extends Command
{
    public function handle(MailboxDriver $buzon, IngestaCorreoService $ingesta): int
    {
        $resultado = $ingesta->procesarPendientes($buzon, (int) ($this->option('limite') ?: config('tramite.correo.lote')));

        $this->info("Procesados: {$resultado['procesados']}. Con error: {$resultado['fallidos']} (se reintentan en la próxima pasada).");

        return $resultado['fallidos'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
