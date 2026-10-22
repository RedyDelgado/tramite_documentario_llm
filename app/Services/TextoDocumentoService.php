<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use ZipArchive;

/** Texto buscable de un documento con capa de texto; los escaneos pasan por el OCR (OcrDocumento). */
class TextoDocumentoService
{
    // Tope del texto guardado por documento: suficiente para buscar, acota el índice.
    private const MAX_CARACTERES = 200_000;

    // Word y Excel son zip con XML: de cada uno, la parte que lleva el texto.
    public const OFFICE = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'word/document.xml',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xl/sharedStrings.xml',
    ];

    /** Devuelve null si el tipo no tiene texto extraíble o la extracción falla. */
    public function extraer(string $rutaAbsoluta, string $mime): ?string
    {
        $texto = match (true) {
            $mime === 'application/pdf' => $this->pdf($rutaAbsoluta),
            str_starts_with($mime, 'text/') => file_get_contents($rutaAbsoluta),
            // Sin su texto, la IA y la búsqueda no veían los Word ni los Excel.
            isset(self::OFFICE[$mime]) => $this->office($rutaAbsoluta, self::OFFICE[$mime]),
            default => null,
        };

        if ($texto === null || $texto === false) {
            return null;
        }

        $texto = trim(preg_replace('/[ \t]+/', ' ', mb_convert_encoding($texto, 'UTF-8', 'UTF-8, ISO-8859-1')));

        // Un escaneo sin capa de texto da solo saltos de página («\f», que trim no quita): eso no es texto, va al OCR.
        return preg_match('/[\p{L}\p{N}]/u', $texto) ? mb_substr($texto, 0, self::MAX_CARACTERES) : null;
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

    private function office(string $ruta, string $parte): ?string
    {
        $zip = new ZipArchive;
        if ($zip->open($ruta, ZipArchive::RDONLY) !== true) {
            return null;
        }
        $xml = $zip->getFromName($parte);
        $zip->close();

        // Fin de párrafo o celda, salto de línea; el resto de etiquetas se van.
        return $xml === false ? null : html_entity_decode(strip_tags(preg_replace('#</(w:p|si)>#', "\n", $xml)), ENT_QUOTES | ENT_XML1, 'UTF-8');
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
