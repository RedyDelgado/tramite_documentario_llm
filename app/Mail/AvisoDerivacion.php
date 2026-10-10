<?php

namespace App\Mail;

use App\Models\Expediente;
use App\Models\ExpedienteArea;
use App\Models\Movimiento;
use App\Models\NotificacionEnviada;
use App\Models\User;
use App\Services\BuzonService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

/** Aviso al momento de derivar (8): a quien debe atender le llega el enlace directo, sin esperar al resumen diario; sin área que atienda, es una copia para conocimiento. */
class AvisoDerivacion extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $destinatario, public Expediente $expediente, public Movimiento $movimiento, public NotificacionEnviada $notificacion, public ?ExpedienteArea $atencion = null) {}

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
        // El código en el asunto enlaza la respuesta del área con su expediente al volver al buzón (7.1).
        return new Envelope(
            from: app(BuzonService::class)->remitente(),
            subject: '['.($this->expediente->codigo ?? 'sin código').'] '.($this->atencion ? 'Para atender: ' : 'En copia, para conocimiento: ').Str::limit($this->expediente->asunto, 80),
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.aviso-derivacion');
    }
}
