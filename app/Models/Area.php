<?php

namespace App\Models;

use Database\Factories\AreaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['nombre', 'descripcion', 'palabras_clave', 'parent_id', 'orden', 'activa'])]
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
}
