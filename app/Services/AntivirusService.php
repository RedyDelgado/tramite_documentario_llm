<?php

namespace App\Services;

use RuntimeException;

/**
 * Análisis con ClamAV (clamd, protocolo INSTREAM) antes de procesar lo que llega de fuera (11).
 * Sin ANTIVIRUS_HOST no analiza: así corre el desarrollo sin el contenedor, que usa ~1,5 GB.
 */
class AntivirusService
{
    private const TROZO = 65536;

    public function activo(): bool
    {
        return (bool) config('tramite.antivirus.host');
    }

    /**
     * Nombre de la amenaza encontrada, o null si el contenido está limpio (o el antivirus está desactivado).
     *
     * @throws RuntimeException si el antivirus está activo y no responde o no puede analizar
     */
    public function amenaza(string $contenido): ?string
    {
        if (! $this->activo()) {
            return null;
        }

        $direccion = 'tcp://'.config('tramite.antivirus.host').':'.config('tramite.antivirus.puerto');
        $socket = @stream_socket_client($direccion, $codigo, $error, 5);
        if (! $socket) {
            throw new RuntimeException("El antivirus no responde en {$direccion}: {$error}");
        }

        try {
            stream_set_timeout($socket, 120);
            fwrite($socket, "zINSTREAM\0");
            for ($i = 0; $i < strlen($contenido); $i += self::TROZO) {
                $trozo = substr($contenido, $i, self::TROZO);
                fwrite($socket, pack('N', strlen($trozo)).$trozo);
            }
            fwrite($socket, pack('N', 0));
            $respuesta = trim((string) stream_get_contents($socket), "\0\n ");
        } finally {
            fclose($socket);
        }

        // clamd responde «stream: OK», «stream: <firma> FOUND» o «<motivo> ERROR».
        if (preg_match('/^stream: (.+) FOUND$/', $respuesta, $m)) {
            return $m[1];
        }
        if ($respuesta === 'stream: OK') {
            return null;
        }

        throw new RuntimeException("El antivirus no pudo analizar el archivo: {$respuesta}");
    }
}
