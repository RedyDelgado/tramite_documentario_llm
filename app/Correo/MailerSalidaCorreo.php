<?php

namespace App\Correo;

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mime\Email;

/** Envío por el mailer configurado de Laravel (log en desarrollo, SMTP si se configura). */
class MailerSalidaCorreo implements SalidaCorreo
{
    public function enviar(Email $email): array
    {
        $enviado = Mail::mailer()->getSymfonyTransport()->send($email);

        return ['message_id' => $enviado->getMessageId(), 'proveedor_id' => null];
    }
}
