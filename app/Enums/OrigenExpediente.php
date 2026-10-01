<?php

namespace App\Enums;

enum OrigenExpediente: string
{
    case Correo = 'correo';
    case Fisico = 'fisico';
    case Pdf = 'pdf';
}
