<?php

namespace App\Jobs;

use App\Enums\EstadoExpediente;
use App\Models\Expediente;
use App\Services\ClasificacionService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

/** Clasifica un expediente con la IA, fuera del ingreso: si la IA está caída, solo se reintenta (principio 2). */
class ClasificarExpediente implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public function backoff(): array
    {
        return [60, 300, 900, 3600, 7200];
    }

    public function __construct(public int $expedienteId) {}

    public function uniqueId(): string
    {
        return (string) $this->expedienteId;
    }

    public function handle(ClasificacionService $clasificacion): void
    {
        $expediente = Expediente::find($this->expedienteId);
        // Lo archivado o anulado no se clasifica: no tendrá decisión humana con la que compararse.
        if (! $expediente || in_array($expediente->estado, [EstadoExpediente::NoTramite, EstadoExpediente::Anulado, EstadoExpediente::Historico], true)) {
            return;
        }

        try {
            $clasificacion->clasificar($expediente);
        } catch (RequestException $e) {
            Log::warning('La IA rechazó una clasificación.', ['expediente' => $expediente->id, 'estado' => $e->response->status()]);
        }
    }
}
