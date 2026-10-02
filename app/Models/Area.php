<?php

namespace App\Models;

use App\Policies\ConfiguracionPolicy;
use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UsePolicy(ConfiguracionPolicy::class)]
#[Fillable(['nombre', 'siglas', 'descripcion', 'palabras_clave', 'parent_id', 'orden', 'activa'])]
class Area extends Model
{
    /** @use HasFactory<AreaFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'palabras_clave' => 'array',
            'activa' => 'boolean',
            'orden' => 'integer',
        ];
    }

    /** @return BelongsTo<Area, $this> */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'parent_id');
    }

    /** @return HasMany<Area, $this> */
    public function hijas(): HasMany
    {
        return $this->hasMany(Area::class, 'parent_id');
    }

    /**
     * Áreas activas para un Select; una inactiva no recibe nada nuevo.
     *
     * @return list<array{value: int, label: string}>
     */
    public static function opciones(?int $excepto = null): array
    {
        return self::where('activa', true)
            ->when($excepto, fn ($q) => $q->whereKeyNot($excepto))
            ->orderBy('orden')->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn (Area $a) => ['value' => $a->id, 'label' => $a->nombre])
            ->all();
    }
}
