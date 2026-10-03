<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaDeNegocio;
use App\Models\Correo;
use App\Models\Documento;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Descarga de originales: solo por aquí, con permiso sobre el expediente y registro en auditoría (11). */
class OriginalController extends Controller
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function documento(Documento $documento): StreamedResponse
    {
        Gate::authorize('view', $documento->expediente);
        if ($documento->amenaza) {
            throw new ReglaDeNegocio("«{$documento->nombre_original}» contiene «{$documento->amenaza}»: está en cuarentena y no se descarga.");
        }
        $this->auditoria->registrar('documento.descargado', $documento, despues: ['sha256' => $documento->sha256]);

        return Storage::disk('originales')->download($documento->ruta, $documento->nombre_original);
    }

    public function correo(Correo $correo): StreamedResponse
    {
        Gate::authorize('view', $correo->expediente);
        // El .eml trae dentro el adjunto infectado.
        if ($correo->documentos()->whereNotNull('amenaza')->exists()) {
            throw new ReglaDeNegocio('Este correo trae un adjunto infectado: el original queda en cuarentena y no se descarga.');
        }
        $this->auditoria->registrar('correo.descargado', $correo, despues: ['sha256' => $correo->sha256]);

        return Storage::disk('originales')->download($correo->ruta_eml, "correo-{$correo->id}.eml");
    }
}
