<?php

namespace App\Correo;

use Carbon\CarbonImmutable;
use ZBateson\MailMimeParser\Header\AddressHeader;
use ZBateson\MailMimeParser\Header\DateHeader;
use ZBateson\MailMimeParser\Header\HeaderConsts;
use ZBateson\MailMimeParser\Header\IdHeader;
use ZBateson\MailMimeParser\IMessage;
use ZBateson\MailMimeParser\MailMimeParser;

/** Traduce un .eml crudo a MensajeLeido; el MIME lo resuelve zbateson/mail-mime-parser. */
class LectorEml
{
    public function leer(string $eml): MensajeLeido
    {
        $mensaje = (new MailMimeParser)->parse($eml, false);
        $de = $mensaje->getHeader(HeaderConsts::FROM);

        return new MensajeLeido(
            messageId: $this->ids($mensaje, HeaderConsts::MESSAGE_ID)[0] ?? null,
            enRespuestaA: array_values(array_unique([
                ...$this->ids($mensaje, HeaderConsts::IN_REPLY_TO),
                ...$this->ids($mensaje, HeaderConsts::REFERENCES),
            ])),
            deEmail: mb_strtolower($de instanceof AddressHeader ? (string) $de->getEmail() : ''),
            deNombre: $de instanceof AddressHeader ? ($de->getPersonName() ?: null) : null,
            para: $this->direcciones($mensaje, HeaderConsts::TO),
            cc: $this->direcciones($mensaje, HeaderConsts::CC),
            asunto: trim((string) $mensaje->getHeaderValue(HeaderConsts::SUBJECT)),
            fecha: $this->fecha($mensaje),
            cuerpo: $this->cuerpo($mensaje),
            encabezados: $this->encabezados($mensaje),
            adjuntos: $this->adjuntos($mensaje),
        );
    }

    /** @return list<string> */
    private function ids(IMessage $mensaje, string $nombre): array
    {
        $encabezado = $mensaje->getHeader($nombre);

        return $encabezado instanceof IdHeader ? array_values(array_filter($encabezado->getIds())) : [];
    }

    /** @return list<array{email: string, nombre: ?string}> */
    private function direcciones(IMessage $mensaje, string $nombre): array
    {
        $encabezado = $mensaje->getHeader($nombre);
        if (! $encabezado instanceof AddressHeader) {
            return [];
        }

        return array_map(
            fn ($d) => ['email' => mb_strtolower((string) $d->getEmail()), 'nombre' => $d->getName() ?: null],
            $encabezado->getAddresses(),
        );
    }

    private function fecha(IMessage $mensaje): ?CarbonImmutable
    {
        $encabezado = $mensaje->getHeader(HeaderConsts::DATE);
        $fecha = $encabezado instanceof DateHeader ? $encabezado->getDateTime() : null;

        return $fecha ? CarbonImmutable::instance($fecha) : null;
    }

    private function cuerpo(IMessage $mensaje): string
    {
        $texto = $mensaje->getTextContent();
        if ($texto === null && ($html = $mensaje->getHtmlContent()) !== null) {
            $texto = html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/div)[^>]*>/i', "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        return trim(preg_replace("/\r\n?/", "\n", (string) $texto));
    }

    /** @return array<string, string> */
    private function encabezados(IMessage $mensaje): array
    {
        $encabezados = [];
        foreach ($mensaje->getAllHeaders() as $encabezado) {
            $encabezados[mb_strtolower($encabezado->getName())] ??= (string) $encabezado->getRawValue();
        }

        return $encabezados;
    }

    /** @return list<Adjunto> */
    private function adjuntos(IMessage $mensaje): array
    {
        $adjuntos = [];
        foreach ($mensaje->getAllAttachmentParts() as $i => $parte) {
            $contenido = $parte->getBinaryContentStream()?->getContents();
            if ($contenido === null || $contenido === '') {
                continue;
            }
            $adjuntos[] = new Adjunto(
                nombre: $parte->getFilename() ?: 'adjunto-'.($i + 1),
                mime: mb_strtolower($parte->getContentType() ?: 'application/octet-stream'),
                contenido: $contenido,
            );
        }

        return $adjuntos;
    }
}
