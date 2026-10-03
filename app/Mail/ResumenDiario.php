<?php

namespace App\Mail;

use App\Models\Expediente;
use App\Models\NotificacionEnviada;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/** Pendientes de un coordinador con enlace directo a cada expediente (8): su entorno de trabajo es el correo. */
class ResumenDiario extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param Collection<int, Expediente> $expedientes */
    public function __construct(public User $coordinador, public Collection $expedientes, public NotificacionEnviada $notificacion) {}

    /** El Message-ID propio enlaza el rebote con su notificación (EntregaService). */
    public function headers(): Headers
    {
        return new Headers(messageId: $this->notificacion->message_id);
    }

    /** Agotados los reintentos de la cola: queda fallido y visible para quien administra. */
    public function failed(Throwable $e): void
    {
        $this->notificacion->forceFill(['estado' => 'fallido', 'detalle' => Str::limit($e->getMessage(), 500)])->save();
    }

    public function envelope(): Envelope
    {
        $rojos = $this->expedientes->where('semaforo.value', 'rojo')->count();

        return new Envelope(subject: "Trámites pendientes: {$this->expedientes->count()}".($rojos ? " ({$rojos} en rojo)" : ''));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.resumen-diario');
    }
}
