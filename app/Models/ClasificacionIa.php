<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Propuesta de la IA para un expediente y la decisión humana con la que se compara (10). */
#[Table('clasificaciones_ia')]
#[Fillable([
    'expediente_id', 'modelo', 'version', 'texto_sha256', 'resultado', 'area_id', 'confianza_area', 'tipo_tramite_id', 'confianza_tipo', 'modo',
])]
class ClasificacionIa extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'resultado' => 'array',
            'confianza_area' => 'float',
            'confianza_tipo' => 'float',
            'acierto_area' => 'boolean',
            'acierto_tipo' => 'boolean',
            'decidido_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Expediente, $this> */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** @return BelongsTo<TipoTramite, $this> */
    public function tipoTramite(): BelongsTo
    {
        return $this->belongsTo(TipoTramite::class);
    }
}
