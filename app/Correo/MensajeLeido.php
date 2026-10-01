<?php

namespace App\Correo;

use Carbon\CarbonImmutable;

/** Lo que el sistema necesita de un .eml, ya decodificado. */
final readonly class MensajeLeido
{
    /**
     * @param  list<string>  $enRespuestaA  Message-IDs de In-Reply-To y References.
     * @param  list<array{email: string, nombre: ?string}>  $para
     * @param  list<array{email: string, nombre: ?string}>  $cc
     * @param  array<string, string>  $encabezados  Nombre en minúsculas => valor crudo.
     * @param  list<Adjunto>  $adjuntos
     */
    public function __construct(
        public ?string $messageId,
        public array $enRespuestaA,
        public string $deEmail,
        public ?string $deNombre,
        public array $para,
        public array $cc,
        public string $asunto,
        public ?CarbonImmutable $fecha,
        public string $cuerpo,
        public array $encabezados,
        public array $adjuntos,
    ) {}
}
