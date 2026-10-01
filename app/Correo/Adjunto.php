<?php

namespace App\Correo;

final readonly class Adjunto
{
    public function __construct(
        public string $nombre,
        public string $mime,
        public string $contenido,
    ) {}
}
