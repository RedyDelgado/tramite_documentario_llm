<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/** Texto buscable de un documento con capa de texto; los escaneos pasan por el OCR (OcrDocumento). */
class TextoDocumentoService
{
    // Tope del texto guardado por documento: suficiente para buscar, acota el índice.
    private const MAX_CARACTERES = 200_000;

    /** Devuelve null si el tipo no tiene texto extraíble o la extracción falla. */
    public function extraer(string $rutaAbsoluta, string $mime): ?string
    {
        $texto = match (true) {
            $mime === 'application/pdf' => $this->pdf($rutaAbsoluta),
            str_starts_with($mime, 'text/') => file_get_contents($rutaAbsoluta),
            default => null,
        };

        if ($texto === null || $texto === false) {
            return null;
        }

        $texto = trim(preg_replace('/[ \t]+/', ' ', mb_convert_encoding($texto, 'UTF-8', 'UTF-8, ISO-8859-1')));

        return $texto === '' ? null : mb_substr($texto, 0, self::MAX_CARACTERES);
    }

    /** Páginas del archivo: folios por defecto del registro de papel (7.3.5, punto 1). */
    public function paginas(string $rutaAbsoluta, string $mime): ?int
    {
        if (str_starts_with($mime, 'image/')) {
            return 1;
        }
        if ($mime !== 'application/pdf') {
            return null;
        }
        $resultado = Process::timeout(30)->run(['pdfinfo', $rutaAbsoluta]);

        return $resultado->successful() && preg_match('/^Pages:\s+(\d+)/m', $resultado->output(), $m) ? (int) $m[1] : null;
    }

    private function pdf(string $ruta): ?string
    {
        $resultado = Process::timeout(60)->run(['pdftotext', '-enc', 'UTF-8', '-q', $ruta, '-']);

        if ($resultado->failed()) {
            Log::warning('pdftotext no pudo leer un documento.', ['codigo' => $resultado->exitCode()]);

            return null;
        }

        return $resultado->output();
    }
}
