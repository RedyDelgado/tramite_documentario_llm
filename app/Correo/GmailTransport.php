<?php

namespace App\Correo;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Correo del sistema (resumen diario, avisos) por Gmail API con la cuenta del buzón central (MAIL_MAILER=gmail):
 * la misma autorización que el buzón, sin SMTP ni otra credencial.
 */
class GmailTransport extends AbstractTransport
{
    public function __construct(private readonly GmailMailboxDriver $gmail)
    {
        parent::__construct();
    }

    // ponytail: Gmail toma los destinatarios de las cabeceras y Symfony no escribe Bcc en el mensaje crudo; ningún correo del sistema usa copia oculta.
    protected function doSend(SentMessage $message): void
    {
        $this->gmail->enviarCrudo($message->toString());
    }

    public function __toString(): string
    {
        return 'gmail';
    }
}
