<?php

namespace App\Correo;

use Symfony\Component\Mime\Email;

/** Envío por Gmail API desde la cuenta del buzón central (7.3.4); el scope gmail.modify ya permite enviar. */
class GmailSalidaCorreo implements SalidaCorreo
{
    public function __construct(private readonly GmailMailboxDriver $gmail) {}

    public function enviar(Email $email): array
    {
        $id = $this->gmail->enviarCrudo($email->toString());

        // Gmail puede reescribir el Message-ID: se lee el definitivo para enlazar respuestas y rebotes.
        return ['message_id' => $this->gmail->messageId($id) ?? $email->getHeaders()->getHeaderBody('Message-ID'), 'proveedor_id' => $id];
    }
}
