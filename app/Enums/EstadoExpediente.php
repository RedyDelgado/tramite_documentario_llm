<?php

namespace App\Enums;

/** Estados de 6.1; `atendido` lo marca el sistema y `cerrado` exige aprobación. */
enum EstadoExpediente: string
{
    case PorRevisar = 'por_revisar';
    case Registrado = 'registrado';
    case Derivado = 'derivado';
    case EnAtencion = 'en_atencion';
    case Atendido = 'atendido';
    case Cerrado = 'cerrado';
    case NoTramite = 'no_tramite';
    case Historico = 'historico';
    case Anulado = 'anulado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::PorRevisar => 'Por revisar',
            self::Registrado => 'Registrado',
            self::Derivado => 'Derivado',
            self::EnAtencion => 'En atención',
            self::Atendido => 'Atendido',
            self::Cerrado => 'Cerrado',
            self::NoTramite => 'No es trámite',
            self::Historico => 'Histórico',
            self::Anulado => 'Anulado',
        };
    }

    /** Solo estos pueden confirmarse como trámite y recibir número (6.2). */
    public function puedeConfirmarse(): bool
    {
        return in_array($this, [self::PorRevisar, self::Historico], true);
    }
}
