<?php

namespace App\Services;

use App\Enums\EstadoExpediente;
use App\Jobs\ClasificarExpediente;
use App\Jobs\EnviarDocumento;
use App\Jobs\OcrDocumento;
use App\Models\Documento;
use App\Models\Envio;
use App\Models\Expediente;
use Illuminate\Support\Facades\Storage;

/**
 * Vuelve a encolar el trabajo pendiente según la base, por si la cola (Redis) se perdió.
 * Los tres jobs son únicos: lo que sigue en la cola no se duplica.
 */
class ColaService
{
    public function __construct(private AuditoriaService $auditoria, private TextoDocumentoService $texto) {}

    /** @return array{envios: int, ocr: int, clasificaciones: int} */
    public function reencolar(): array
    {
        $envios = Envio::where('estado', 'pendiente')
            ->whereHas('saliente', fn ($q) => $q->where('estado', 'aprobado')
                ->where(fn ($q) => $q->where('esperar_firma', false)->orWhereNotNull('ruta_firmado')))
            ->pluck('id')
            ->each(fn ($id) => EnviarDocumento::dispatch($id));

        $ocr = Documento::whereNull('texto_extraido')->whereIn('mime', OcrService::MIMES)
            ->pluck('id')
            ->each(fn ($id) => OcrDocumento::dispatch($id));

        // Word y Excel de antes de que se leyera su texto: se leen aquí mismo, sin cola.
        Documento::whereNull('texto_extraido')->whereIn('mime', array_keys(TextoDocumentoService::OFFICE))->get()
            ->each(fn (Documento $d) => $d->forceFill(['texto_extraido' => $this->texto->extraer(Storage::disk('originales')->path($d->ruta), $d->mime)])->save());

        $clasificaciones = Expediente::whereNotNull('secuencia')
            ->whereNotIn('estado', [EstadoExpediente::NoTramite, EstadoExpediente::Anulado, EstadoExpediente::Historico])
            ->whereDoesntHave('clasificaciones')
            ->pluck('id')
            ->each(fn ($id) => ClasificarExpediente::dispatch($id));

        $totales = ['envios' => $envios->count(), 'ocr' => $ocr->count(), 'clasificaciones' => $clasificaciones->count()];
        if (array_sum($totales) > 0) {
            $this->auditoria->registrar('colas.reencoladas', 'cola', despues: $totales);
        }

        return $totales;
    }
}
