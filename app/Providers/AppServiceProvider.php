<?php

namespace App\Providers;

use App\Correo\DirectorioMailboxDriver;
use App\Correo\GmailMailboxDriver;
use App\Correo\GmailSalidaCorreo;
use App\Correo\GmailTransport;
use App\Correo\MailboxDriver;
use App\Correo\MailerSalidaCorreo;
use App\Correo\SalidaCorreo;
use App\Models\NotificacionEnviada;
use App\Models\User;
use App\Services\BuzonService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // El driver del buzón se elige por configuración; el resto del sistema solo ve la interfaz (7.1).
        // El buzón conectado desde el panel manda sobre el .env (BuzonService).
        $this->app->bind(MailboxDriver::class, fn ($app) => match ($app->make(BuzonService::class)->driver()) {
            'directorio' => new DirectorioMailboxDriver(config('tramite.correo.directorio')),
            'gmail' => new GmailMailboxDriver($app->make(BuzonService::class)->gmail()),
            default => throw new InvalidArgumentException('CORREO_DRIVER desconocido: '.config('tramite.correo.driver')),
        });
        $this->app->bind(SalidaCorreo::class, fn () => match (config('tramite.salientes.driver')) {
            'mailer' => new MailerSalidaCorreo,
            'gmail' => new GmailSalidaCorreo(new GmailMailboxDriver(app(BuzonService::class)->gmail())),
            default => throw new InvalidArgumentException('SALIENTES_DRIVER desconocido: '.config('tramite.salientes.driver')),
        });
    }

    public function boot(): void
    {
        // Un correo por destinatario, a ritmo controlado (7.3.4).
        RateLimiter::for('envios', fn () => Limit::perMinute(config('tramite.salientes.por_minuto')));

        Mail::extend('gmail', fn () => new GmailTransport(new GmailMailboxDriver(app(BuzonService::class)->gmail())));

        // El buzón institucional lo conecta y administra solo el superadmin (sus credenciales dan acceso a todo el correo).
        Gate::define('gestionar-buzon', fn (User $user) => $user->hasRole('superadmin'));

        // Salió del transporte: la notificación con ese Message-ID queda enviada (un rebote posterior la corrige).
        Event::listen(function (MessageSent $evento) {
            $id = trim((string) $evento->message->getHeaders()->get('Message-ID')?->getBodyAsString(), '<>');
            NotificacionEnviada::where('message_id', $id)->where('estado', '!=', 'rebotado')
                ->update(['estado' => 'enviado', 'enviado_at' => now(), 'detalle' => null]);
        });
    }
}
