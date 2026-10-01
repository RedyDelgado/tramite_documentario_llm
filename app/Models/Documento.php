<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'expediente_id', 'correo_id', 'version', 'nombre_original', 'ruta', 'mime', 'tamano', 'sha256',
    'texto_extraido', 'es_adjunto',
])]
class Documento extends Model
{
    protected function casts(): array
    {
        return ['es_adjunto' => 'boolean', 'tamano' => 'integer', 'version' => 'integer'];
    }

    /** @return BelongsTo<Expediente, $this> */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }
}
