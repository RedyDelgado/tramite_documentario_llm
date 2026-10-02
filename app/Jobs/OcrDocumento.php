<?php

namespace App\Jobs;

use App\Models\Documento;
use App\Services\AuditoriaService;
use App\Services\OcrService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/** Texto de un escaneo vía OCR; idempotente y con reintentos si el servicio de IA está caído. */
class OcrDocumento implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    // Hasta ~6 h de espera acumulada: un reinicio del servicio de IA no pierde OCR.
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 7200];
    }

    public function __construct(public int $documentoId) {}

    public function uniqueId(): string
    {
        return (string) $this->documentoId;
    }

    public function handle(OcrService $ocr, AuditoriaService $auditoria): void
    {
        $documento = Documento::find($this->documentoId);
        if (! $documento || $documento->texto_extraido !== null) {
            return;
        }

        try {
            $resultado = $ocr->reconocer(Storage::disk('originales')->path($documento->ruta), $documento->mime);
        } catch (RequestException $e) {
            // El servicio respondió pero no pudo leerlo (dañado, tipo raro): reintentar no ayuda.
            Log::warning('OCR rechazado.', ['documento' => $documento->id, 'estado' => $e->response->status()]);

            return;
        }

        $texto = trim($resultado['texto']);
        $documento->forceFill([
            'texto_extraido' => $texto === '' ? null : mb_substr($texto, 0, 200_000),
            'texto_por_ocr' => $texto !== '',
            'paginas' => $documento->paginas ?? $resultado['paginas'],
        ])->save();
        $auditoria->registrar('documento.ocr', $documento, despues: ['caracteres' => mb_strlen($texto), 'paginas' => $resultado['paginas']]);

        // El texto del escaneo entra a la búsqueda (7.4).
        $documento->expediente->searchable();
    }
}
