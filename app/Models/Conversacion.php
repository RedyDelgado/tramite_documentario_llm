<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nombre', 'clave', 'creada_por', 'ultimo_mensaje_at'])]
class Conversacion extends Model
{
    protected $table = 'conversaciones';

    protected function casts(): array
    {
        return ['ultimo_mensaje_at' => 'datetime'];
    }

    /** @return BelongsToMany<User, $this> */
    public function participantes(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversacion_usuario')->withPivot('leido_hasta')->withTimestamps();
    }

    /** @return HasMany<Mensaje, $this> */
    public function mensajes(): HasMany
    {
        return $this->hasMany(Mensaje::class);
    }

    /** Clave de una conversación de persona a persona: la misma sin importar quién la empiece. */
    public static function claveEntre(int $a, int $b): string
    {
        return min($a, $b).'-'.max($a, $b);
    }

    public function esGrupo(): bool
    {
        return $this->clave === null;
    }

    public function tieneA(User $user): bool
    {
        return $this->participantes()->whereKey($user->id)->exists();
    }

    /** @param Builder<self> $query */
    public function scopeDe(Builder $query, User $user): void
    {
        $query->whereHas('participantes', fn ($q) => $q->whereKey($user->id));
    }
}
