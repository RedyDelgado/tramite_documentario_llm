<?php

namespace App\Models;

use App\Policies\ConfiguracionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Titular o suplente de un área con vigencia (5.1); da visibilidad sobre los expedientes del área. */
#[UsePolicy(ConfiguracionPolicy::class)]
#[Fillable(['area_id', 'user_id', 'tipo', 'vigente_desde', 'vigente_hasta'])]
class AreaResponsable extends Model
{
    public const TIPOS = ['titular' => 'Titular', 'suplente' => 'Suplente'];

    protected function casts(): array
    {
        return ['vigente_desde' => 'date:Y-m-d', 'vigente_hasta' => 'date:Y-m-d'];
    }

    /** @param Builder<AreaResponsable> $query */
    public function scopeVigentes(Builder $query): void
    {
        $hoy = today();
        $query->whereDate('vigente_desde', '<=', $hoy)
            ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $hoy));
    }

    public function esVigente(): bool
    {
        return $this->vigente_desde->lte(today()) && ($this->vigente_hasta === null || $this->vigente_hasta->gte(today()));
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
