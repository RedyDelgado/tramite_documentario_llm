<?php

namespace App\Models;

use App\Policies\EmisorPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Dependencia que emite el documento (6.1), interna o externa. */
#[UsePolicy(EmisorPolicy::class)]
#[Table('emisores')]
#[Fillable(['nombre', 'tipo', 'activo'])]
class Emisor extends Model
{
    public const TIPOS = ['interno' => 'Interno', 'externo' => 'Externo'];

    // Similitud de trigramas desde la que dos nombres se proponen como posible duplicado.
    public const UMBRAL_PARECIDO = 0.5;

    protected $attributes = ['tipo' => 'externo', 'activo' => true];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $e) => $e->nombre_normalizado = self::normalizar($e->nombre));
    }

    public static function normalizar(string $nombre): string
    {
        return Str::of($nombre)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
    }

    /**
     * Vigente: ni inactivo ni fusionado en otro.
     *
     * @param  Builder<Emisor>  $query
     */
    public function scopeVigentes(Builder $query): void
    {
        $query->where('activo', true)->whereNull('fusionado_en_id');
    }

    /** @return list<array{value: int, label: string}> */
    public static function opciones(): array
    {
        return self::vigentes()->orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (self $e) => ['value' => $e->id, 'label' => $e->nombre])
            ->all();
    }

    /** Emisor no fusionado con el mismo nombre normalizado, si existe. */
    public static function mismoNombre(string $nombre, ?int $excepto = null): ?self
    {
        return self::whereNull('fusionado_en_id')->where('nombre_normalizado', self::normalizar($nombre))
            ->when($excepto, fn ($q) => $q->whereKeyNot($excepto))->first();
    }

    /**
     * Emisores no fusionados con nombre parecido, del más al menos parecido.
     *
     * @return Collection<int, self>
     */
    public static function parecidos(string $nombre, ?int $excepto = null): Collection
    {
        return self::whereNull('fusionado_en_id')
            ->whereRaw('similarity(nombre_normalizado, ?) >= ?', [self::normalizar($nombre), self::UMBRAL_PARECIDO])
            ->when($excepto, fn ($q) => $q->whereKeyNot($excepto))
            ->orderByRaw('similarity(nombre_normalizado, ?) DESC', [self::normalizar($nombre)])
            ->limit(5)
            ->get();
    }

    /**
     * Pares de emisores vigentes que podrían ser el mismo (7.3.5).
     *
     * @return list<array{a: array{id: int, nombre: string}, b: array{id: int, nombre: string}}>
     */
    public static function posiblesDuplicados(): array
    {
        // ponytail: autounión O(n²), suficiente para cientos de emisores; índice GIN de trigramas si crece.
        return collect(DB::select(
            'SELECT a.id AS a_id, a.nombre AS a_nombre, b.id AS b_id, b.nombre AS b_nombre
               FROM emisores a JOIN emisores b ON a.id < b.id
              WHERE a.fusionado_en_id IS NULL AND b.fusionado_en_id IS NULL AND a.activo AND b.activo
                AND similarity(a.nombre_normalizado, b.nombre_normalizado) >= ?
              ORDER BY similarity(a.nombre_normalizado, b.nombre_normalizado) DESC LIMIT 20',
            [self::UMBRAL_PARECIDO],
        ))->map(fn ($f) => ['a' => ['id' => $f->a_id, 'nombre' => $f->a_nombre], 'b' => ['id' => $f->b_id, 'nombre' => $f->b_nombre]])->all();
    }

    /** @return HasMany<Expediente, $this> */
    public function expedientes(): HasMany
    {
        return $this->hasMany(Expediente::class);
    }

    /** @return BelongsTo<Emisor, $this> */
    public function fusionadoEn(): BelongsTo
    {
        return $this->belongsTo(self::class, 'fusionado_en_id');
    }
}
