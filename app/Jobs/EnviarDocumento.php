<?php

namespace App\Jobs;

use App\Models\Envio;
use App\Services\EnvioService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Throwable;

/** Un correo de un documento aprobado a un destinatario, a ritmo controlado (7.3.4). */
class EnviarDocumento implements ShouldQueue
{
    use Queueable;

    // El limitador de ritmo reencola sin contar como fallo: se reintenta por tiempo, no por número de intentos.
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addDay();
    }

    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    public function __construct(public int $envioId) {}

    /** @return list<object> */
    public function middleware(): array
    {
        return [new RateLimited('envios')];
    }

    public function handle(EnvioService $envios): void
    {
        $envios->enviar(Envio::findOrFail($this->envioId));
    }

    public function failed(Throwable $e): void
    {
        app(EnvioService::class)->fallo(Envio::findOrFail($this->envioId), $e);
    }
}
