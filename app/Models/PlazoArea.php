<?php

namespace App\Models;

use App\Policies\ConfiguracionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Plazo distinto para un tipo de trámite en un área; manda sobre el del tipo (5.1). */
#[UsePolicy(ConfiguracionPolicy::class)]
#[Table('plazos_area')]
#[Fillable(['tipo_tramite_id', 'area_id', 'plazo_dias'])]
class PlazoArea extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return ['plazo_dias' => 'integer'];
    }

    /** @return BelongsTo<TipoTramite, $this> */
    public function tipoTramite(): BelongsTo
    {
        return $this->belongsTo(TipoTramite::class);
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
