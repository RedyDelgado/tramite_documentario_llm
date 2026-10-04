<?php

namespace App\Mail;

use App\Models\Expediente;
use App\Models\Movimiento;
use App\Models\NotificacionEnviada;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

/** Aviso al momento de derivar (8): a quien debe atender le llega el enlace directo, sin esperar al resumen diario. */
class AvisoDerivacion extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $destinatario, public Expediente $expediente, public Movimiento $movimiento, public NotificacionEnviada $notificacion) {}

    /** El Message-ID propio enlaza el rebote con su notificación (EntregaService). */
    public function headers(): Headers
    {
        return new Headers(messageId: $this->notificacion->message_id);
    }

    public function failed(Throwable $e): void
    {
        $this->notificacion->forceFill(['estado' => 'fallido', 'detalle' => Str::limit($e->getMessage(), 500)])->save();
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Nuevo trámite para atender: '.($this->expediente->numero_registro ?? 'sin número').' · '.Str::limit($this->expediente->asunto, 80));
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.aviso-derivacion');
    }
}
