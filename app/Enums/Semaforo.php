<?php

namespace App\Enums;

/** Semáforo de la sección 8; los valores coinciden con SemaforoBadge. */
enum Semaforo: string
{
    case Verde = 'verde';
    case Amarillo = 'amarillo';
    case Rojo = 'rojo';
    case Gris = 'gris';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Verde => 'En plazo',
            self::Amarillo => 'Por vencer',
            self::Rojo => 'Vencido o sin responsable',
            self::Gris => 'Pendiente de clasificar',
        };
    }
}
