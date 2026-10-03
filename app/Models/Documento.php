<?php

namespace App\Models;

use App\Jobs\OcrDocumento;
use App\Services\OcrService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'expediente_id', 'correo_id', 'version', 'nombre_original', 'ruta', 'mime', 'tamano', 'paginas', 'sha256',
    'texto_extraido', 'es_adjunto', 'amenaza',
])]
class Documento extends Model
{
    protected function casts(): array
    {
        return ['es_adjunto' => 'boolean', 'tamano' => 'integer', 'paginas' => 'integer', 'version' => 'integer', 'texto_por_ocr' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Un PDF sin capa de texto o una imagen es un escaneo: su texto sale del OCR, en cola (7.3).
        static::created(function (self $documento) {
            // Lo que está en cuarentena no se procesa (11).
            if ($documento->amenaza === null && $documento->texto_extraido === null && in_array($documento->mime, OcrService::MIMES, true)) {
                OcrDocumento::dispatch($documento->id)->afterCommit();
            }
        });
    }

    /**
     * Guarda un archivo original una sola vez: el nombre es su hash, así que uno existente ya es idéntico (2).
     *
     * @return array{ruta: string, sha256: string}
     */
    public static function guardarArchivo(string $contenido, string $carpeta = 'adjuntos'): array
    {
        $sha256 = hash('sha256', $contenido);
        $ruta = $carpeta.'/'.substr($sha256, 0, 2).'/'.$sha256;
        $disco = Storage::disk('originales');
        if (! $disco->exists($ruta)) {
            $disco->put($ruta, $contenido);
        }

        return ['ruta' => $ruta, 'sha256' => $sha256];
    }

    /** @return BelongsTo<Expediente, $this> */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }
}
