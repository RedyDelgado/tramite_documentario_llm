<?php

namespace App\Mail;

use App\Models\Expediente;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/** Pendientes de un coordinador con enlace directo a cada expediente (8): su entorno de trabajo es el correo. */
class ResumenDiario extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param Collection<int, Expediente> $expedientes */
    public function __construct(public User $coordinador, public Collection $expedientes) {}

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
