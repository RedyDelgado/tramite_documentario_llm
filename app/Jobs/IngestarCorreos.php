<?php

namespace App\Jobs;

use App\Correo\MailboxDriver;
use App\Services\IngestaCorreoService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Lee un lote del buzón central; programado cada minuto. Idempotente por Message-ID. */
class IngestarCorreos implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    // Una sola lectura del buzón a la vez.
    public int $uniqueFor = 600;

    public function handle(MailboxDriver $buzon, IngestaCorreoService $ingesta): void
    {
        $ingesta->procesarPendientes($buzon, config('tramite.correo.lote'));
    }
}
