<?php

namespace App\Correo;

use Symfony\Component\Mime\Email;

/** Envío de correo saliente (7.3.4), intercambiable como MailboxDriver: Gmail API en producción, mailer de Laravel en desarrollo. */
interface SalidaCorreo
{
    /**
     * @return array{message_id: string, proveedor_id: ?string} Message-ID definitivo (para enlazar respuestas y rebotes)
     */
    public function enviar(Email $email): array;
}
