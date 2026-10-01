<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Titular o suplente de un área con vigencia (5.1); la gestión desde el panel llega en la fase 2. */
#[Fillable(['area_id', 'user_id', 'tipo', 'vigente_desde', 'vigente_hasta'])]
class AreaResponsable extends Model
{
    protected function casts(): array
    {
        return ['vigente_desde' => 'date', 'vigente_hasta' => 'date'];
    }

    /** @param Builder<AreaResponsable> $query */
    public function scopeVigentes(Builder $query): void
    {
        $hoy = today();
        $query->whereDate('vigente_desde', '<=', $hoy)
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $hoy));
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }
}
