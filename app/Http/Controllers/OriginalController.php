<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaDeNegocio;
use App\Models\Correo;
use App\Models\Documento;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZBateson\MailMimeParser\MailMimeParser;

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
        $this->autorizarOriginal($correo);
        $this->auditoria->registrar('correo.descargado', $correo, despues: ['sha256' => $correo->sha256]);

        return Storage::disk('originales')->download($correo->ruta_eml, "correo-{$correo->id}.eml");
    }

    /**
     * El correo como llegó (HTML con sus imágenes incrustadas), para verlo dentro de un iframe aislado: sin scripts ni
     * formularios, y las imágenes externas bloqueadas hasta pedirlas (avisan al remitente que se abrió, como en Gmail).
     */
    public function vista(Correo $correo, Request $request): Response
    {
        $this->autorizarOriginal($correo);
        $mensaje = (new MailMimeParser)->parse(Storage::disk('originales')->get($correo->ruta_eml), false);

        $html = $mensaje->getHtmlContent() ?? '<pre style="white-space:pre-wrap;font:14px system-ui">'.e((string) $mensaje->getTextContent()).'</pre>';
        // Las imágenes incrustadas (cid:) van como data: para no abrir otra ruta.
        foreach ($mensaje->getAllParts() as $parte) {
            if ($cid = $parte->getContentId()) {
                $datos = 'data:'.$parte->getContentType().';base64,'.base64_encode((string) $parte->getBinaryContentStream()?->getContents());
                $html = str_ireplace('cid:'.$cid, $datos, $html);
            }
        }

        $imagenes = $request->boolean('imagenes') ? ' https: http:' : '';

        return response('<!doctype html><meta charset="utf-8"><base target="_blank">'.$html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; img-src data:{$imagenes}; sandbox allow-popups allow-popups-to-escape-sandbox",
            'Referrer-Policy' => 'no-referrer',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function autorizarOriginal(Correo $correo): void
    {
        Gate::authorize('view', $correo->expediente);
        // El .eml trae dentro el adjunto infectado.
        if ($correo->documentos()->whereNotNull('amenaza')->exists()) {
            throw new ReglaDeNegocio('Este correo trae un adjunto infectado: el original queda en cuarentena y no se descarga.');
        }
    }
}
