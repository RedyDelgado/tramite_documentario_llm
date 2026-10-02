<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'expediente_id', 'message_id', 'en_respuesta_a', 'uid_externo', 'de_email', 'de_nombre', 'para', 'cc',
    'asunto', 'fecha', 'cuerpo_texto', 'es_reenvio', 'ruta_eml', 'sha256',
])]
// Con desfase: la fecha de un correo llega en la zona del remitente y debe guardarse como instante exacto.
#[DateFormat('Y-m-d H:i:sP')]
class Correo extends Model
{
    protected function casts(): array
    {
        return [
            'en_respuesta_a' => 'array',
            'para' => 'array',
            'cc' => 'array',
            'fecha' => 'datetime',
            'es_reenvio' => 'boolean',
        ];
    }

    /** @return BelongsTo<Expediente, $this> */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    /** @return HasMany<Documento, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class);
    }
}
