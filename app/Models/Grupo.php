<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Serie de documentos relacionados (7.3.1), p. ej. oficios circulares del mismo emisor y asunto el mismo día. */
#[Fillable(['nombre', 'creado_por'])]
class Grupo extends Model
{
    /** @return HasMany<Expediente, $this> */
    public function expedientes(): HasMany
    {
        return $this->hasMany(Expediente::class);
    }
}
