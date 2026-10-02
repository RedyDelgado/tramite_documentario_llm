<?php

namespace App\Console\Commands;

use App\Services\ColaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('colas:reencolar')]
#[Description('Vuelve a encolar envíos, OCR y clasificaciones pendientes si la cola se perdió; no duplica lo que sigue en cola')]
class ReencolarPendientes extends Command
{
    public function handle(ColaService $colas): int
    {
        $totales = $colas->reencolar();

        $this->info("Envíos: {$totales['envios']}. OCR: {$totales['ocr']}. Clasificaciones: {$totales['clasificaciones']}.");

        return self::SUCCESS;
    }
}
