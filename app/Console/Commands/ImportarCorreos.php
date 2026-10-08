<?php

namespace App\Console\Commands;

use App\Services\BuzonService;
use App\Services\IngestaCorreoService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('correo:importar {--limite= : Mensajes a procesar (por defecto CORREO_LOTE)}')]
#[Description('Procesa ahora los correos pendientes del buzón central (mismo proceso que el job programado)')]
class ImportarCorreos extends Command
{
    public function handle(BuzonService $buzones, IngestaCorreoService $ingesta): int
    {
        $resultado = ['procesados' => 0, 'fallidos' => 0];
        foreach ($buzones->lectores() as [$nombre, $lector, $cuenta]) {
            $r = $ingesta->procesarPendientes($lector, (int) ($this->option('limite') ?: config('tramite.correo.lote')), $nombre, $cuenta);
            $resultado = ['procesados' => $resultado['procesados'] + $r['procesados'], 'fallidos' => $resultado['fallidos'] + $r['fallidos']];
        }

        $this->info("Procesados: {$resultado['procesados']}. Con error: {$resultado['fallidos']} (se reintentan en la próxima pasada).");

        return $resultado['fallidos'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
