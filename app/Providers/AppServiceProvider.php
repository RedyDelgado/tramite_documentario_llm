<?php

namespace App\Providers;

use App\Correo\DirectorioMailboxDriver;
use App\Correo\MailboxDriver;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // El driver del buzón se elige por configuración; el resto del sistema solo ve la interfaz (7.1).
        $this->app->bind(MailboxDriver::class, fn () => match (config('tramite.correo.driver')) {
            'directorio' => new DirectorioMailboxDriver(config('tramite.correo.directorio')),
            default => throw new InvalidArgumentException('CORREO_DRIVER desconocido: '.config('tramite.correo.driver')),
        });
    }

    public function boot(): void
    {
        //
    }
}
