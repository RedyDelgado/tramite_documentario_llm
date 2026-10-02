<?php

namespace App\Services;

use App\Enums\OrigenExpediente;
use App\Exceptions\ReglaDeNegocio;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\Movimiento;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Control del papel (7.3.1): el original nunca se descarta; su ubicación y custodio quedan auditados. */
class OriginalService
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    /** Cambia ubicación o custodio del original; cada cambio es un movimiento auditado. */
    public function mover(Expediente $expediente, ?int $ubicacionId, int $custodioId, ?string $nota): void
    {
        if ($expediente->origen !== OrigenExpediente::Fisico) {
            throw new ReglaDeNegocio('Solo un documento en papel tiene original físico.');
        }

        DB::transaction(function () use ($expediente, $ubicacionId, $custodioId, $nota) {
            $antes = $expediente->only(['ubicacion_fisica_id', 'custodio_id']);
            $expediente->forceFill(['ubicacion_fisica_id' => $ubicacionId, 'custodio_id' => $custodioId])->save();
            $movimiento = Movimiento::create([
                'expediente_id' => $expediente->id, 'tipo' => 'original_movido', 'user_id' => Auth::id(), 'a_user_id' => $custodioId, 'nota' => $nota,
            ]);
            $this->auditoria->registrar('original.movido', $expediente, antes: $antes, despues: [
                'ubicacion_fisica_id' => $ubicacionId, 'custodio_id' => $custodioId, 'movimiento_id' => $movimiento->id,
            ]);
        });
    }

    /** Adjunta el cargo de entrega firmado y escaneado a su derivación (7.3.1). */
    public function adjuntarCargo(Movimiento $movimiento, UploadedFile $archivo): Documento
    {
        if ($movimiento->tipo !== 'derivacion') {
            throw new ReglaDeNegocio('El cargo de entrega se adjunta a una derivación.');
        }

        return DB::transaction(function () use ($movimiento, $archivo) {
            ['ruta' => $ruta, 'sha256' => $sha256] = Documento::guardarArchivo($archivo->getContent());
            $documento = Documento::create([
                'expediente_id' => $movimiento->expediente_id,
                'nombre_original' => 'Cargo de entrega - '.$archivo->getClientOriginalName(),
                'ruta' => $ruta,
                'mime' => $archivo->getMimeType(),
                'tamano' => $archivo->getSize(),
                'sha256' => $sha256,
                'es_adjunto' => false,
            ]);
            $documento->forceFill(['movimiento_id' => $movimiento->id])->save();
            $this->auditoria->registrar('cargo.adjuntado', $documento, despues: ['movimiento_id' => $movimiento->id, 'sha256' => $sha256]);

            return $documento;
        });
    }

    /** QR en SVG con el enlace del expediente: la cámara de un teléfono o un lector USB lo abren (7.3). */
    public static function qr(Expediente $expediente, int $tamano = 160): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($tamano, 1), new SvgImageBackEnd));

        return $writer->writeString(route('qr', $expediente->codigo));
    }
}
