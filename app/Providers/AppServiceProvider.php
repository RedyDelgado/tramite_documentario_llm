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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // El driver del buzón se elige por configuración; el resto del sistema solo ve la interfaz (7.1).
        $this->app->bind(MailboxDriver::class, fn () => match (config('tramite.correo.driver')) {
            'directorio' => new DirectorioMailboxDriver(config('tramite.correo.directorio')),
            'gmail' => new GmailMailboxDriver(config('tramite.correo.gmail')),
            default => throw new InvalidArgumentException('CORREO_DRIVER desconocido: '.config('tramite.correo.driver')),
        });
        $this->app->bind(SalidaCorreo::class, fn () => match (config('tramite.salientes.driver')) {
            'mailer' => new MailerSalidaCorreo,
            'gmail' => new GmailSalidaCorreo(new GmailMailboxDriver(config('tramite.correo.gmail'))),
            default => throw new InvalidArgumentException('SALIENTES_DRIVER desconocido: '.config('tramite.salientes.driver')),
        });
    }

    public function boot(): void
    {
        // Un correo por destinatario, a ritmo controlado (7.3.4).
        RateLimiter::for('envios', fn () => Limit::perMinute(config('tramite.salientes.por_minuto')));

        Mail::extend('gmail', fn () => new GmailTransport(new GmailMailboxDriver(config('tramite.correo.gmail'))));

        // Salió del transporte: la notificación con ese Message-ID queda enviada (un rebote posterior la corrige).
        Event::listen(function (MessageSent $evento) {
            $id = trim((string) $evento->message->getHeaders()->get('Message-ID')?->getBodyAsString(), '<>');
            NotificacionEnviada::where('message_id', $id)->where('estado', '!=', 'rebotado')
                ->update(['estado' => 'enviado', 'enviado_at' => now(), 'detalle' => null]);
        });
    }
}
