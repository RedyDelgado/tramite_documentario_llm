<?php

namespace App\Correo;

/** Un mensaje tal como llegó: su id en el buzón y el .eml completo. */
final readonly class MensajeCrudo
{
    public function __construct(
        public string $uid,
        public string $contenido,
    ) {}
}
