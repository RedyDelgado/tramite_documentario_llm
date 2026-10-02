<?php

namespace App\Console\Commands;

use App\Services\RespaldoService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('respaldo:crear')]
#[Description('Respalda la base, los originales y los modelos de IA, y borra los respaldos vencidos')]
class CrearRespaldo extends Command
{
    public function handle(RespaldoService $respaldos): int
    {
        $carpeta = $respaldos->crear();
        $borrados = $respaldos->podar();

        $this->info("Respaldo creado en {$carpeta}.".($borrados ? " Se borraron {$borrados} respaldos vencidos." : ''));

        return self::SUCCESS;
    }
}
