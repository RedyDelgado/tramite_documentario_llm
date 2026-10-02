<?php

namespace App\Console\Commands;

use App\Services\SemaforoService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('semaforos:recalcular')]
#[Description('Recalcula el semáforo de los expedientes abiertos (vencimientos y días sin movimiento)')]
class RecalcularSemaforos extends Command
{
    public function handle(SemaforoService $semaforos): int
    {
        $this->info("Semáforos actualizados: {$semaforos->recalcularAbiertos()}.");

        return self::SUCCESS;
    }
}
