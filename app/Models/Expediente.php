<?php

namespace App\Models;

use App\Enums\EstadoExpediente;
use App\Enums\OrigenExpediente;
use App\Enums\Semaforo;
use App\Services\SemaforoService;
use Database\Factories\ExpedienteFactory;
use Illuminate\Database\Eloquent\Attributes\DateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;

#[Fillable([
    'origen', 'estado', 'asunto', 'remitente_nombre', 'remitente_email', 'remitente_por_confirmar',
    'fecha_ingreso', 'area_principal_id', 'responsable_id',
])]
// Con desfase: fecha_ingreso viene del correo, en la zona del remitente (ver Correo).
#[DateFormat('Y-m-d H:i:sP')]
class Expediente extends Model
{
    /** @use HasFactory<ExpedienteFactory> */
    use HasFactory, Searchable;

    // Tope del texto indexado por expediente: cubre oficios largos sin inflar el índice.
    private const MAX_TEXTO_INDICE = 60_000;

    protected static function booted(): void
    {
        // Cada cambio recalcula el semáforo; el paso del tiempo lo cubre semaforos:recalcular (8).
        static::saving(fn (self $e) => $e->semaforo = app(SemaforoService::class)->calcular($e));
    }

    /** @return array<string, mixed> */
    public function toSearchableArray(): array
    {
        $this->loadMissing(['correos', 'documentos']);

        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'numero_registro' => $this->numero_registro,
            'asunto' => $this->asunto,
            'remitente_nombre' => $this->remitente_nombre,
            'remitente_email' => $this->remitente_email,
            'estado' => $this->estado->value,
            'semaforo' => $this->semaforo?->value,
            'fecha_ingreso' => $this->fecha_ingreso->getTimestamp(),
            'visible_para' => $this->tokensVisibilidad(),
            'texto' => Str::limit(
                $this->correos->toBase()->map(fn (Correo $c) => $c->asunto."\n".$c->cuerpo_texto)
                    ->merge($this->documentos->map(fn (Documento $d) => $d->nombre_original."\n".$d->texto_extraido))
                    ->implode("\n"),
                self::MAX_TEXTO_INDICE, '',
            ),
        ];
    }

    /** @param Collection<int, Expediente> $modelos */
    public function makeSearchableUsing(Collection $modelos): Collection
    {
        return $modelos->load(['correos', 'documentos']);
    }

    /** @return list<string> */
    private function tokensVisibilidad(): array
    {
        return array_values(array_filter([
            $this->area_principal_id ? "area:{$this->area_principal_id}" : null,
            $this->responsable_id ? "usuario:{$this->responsable_id}" : null,
        ]));
    }

    /**
     * Tokens que el usuario puede ver en el índice; null si ve todo. Espeja scopeVisiblesPara.
     *
     * @return list<string>|null
     */
    public static function tokensVisiblesPara(User $user): ?array
    {
        if ($user->can('expedientes.ver_todos')) {
            return null;
        }

        $areas = $user->can('expedientes.ver_areas') ? $user->areasVigentes() : [];

        return ["usuario:{$user->id}", ...array_map(fn (int $id) => "area:{$id}", $areas)];
    }

    protected function casts(): array
    {
        return [
            'estado' => EstadoExpediente::class,
            'origen' => OrigenExpediente::class,
            'fecha_ingreso' => 'datetime',
            'registrado_at' => 'datetime',
            'remitente_por_confirmar' => 'boolean',
            'requiere_respuesta' => 'boolean',
            'semaforo' => Semaforo::class,
            'fecha_limite' => 'date:Y-m-d',
            'ultimo_movimiento_at' => 'datetime',
            'cierre_solicitado_at' => 'datetime',
            'atendido_at' => 'datetime',
            'plazo_dias_aplicado' => 'integer',
            'anio' => 'integer',
            'secuencia' => 'integer',
        ];
    }

    /** Formato visible configurable, p. ej. N°00038 (6.2). */
    protected function numeroRegistro(): Attribute
    {
        return Attribute::get(fn () => $this->secuencia ? sprintf(config('tramite.registro.formato'), $this->secuencia) : null);
    }

    /** Código de enlace sin ambigüedad entre años: asunto de correo, QR y URL (6.2). */
    protected function codigo(): Attribute
    {
        return Attribute::get(fn () => $this->secuencia ? sprintf('REG-%d-%05d', $this->anio, $this->secuencia) : null);
    }

    /** Expediente cuyo código REG-AAAA-NNNNN aparece en el texto (p. ej. el asunto de una respuesta). */
    public static function porCodigoEn(string $texto): ?self
    {
        if (! preg_match('/\bREG-(\d{4})-(\d{1,7})\b/i', $texto, $m)) {
            return null;
        }

        return self::where('anio', (int) $m[1])->where('secuencia', (int) $m[2])->first();
    }

    /**
     * Filtro único de visibilidad: todo listado y búsqueda de expedientes pasa por aquí (5.2).
     *
     * @param  Builder<Expediente>  $query
     */
    public function scopeVisiblesPara(Builder $query, User $user): void
    {
        if ($user->can('expedientes.ver_todos')) {
            return;
        }

        $areas = $user->can('expedientes.ver_areas') ? $user->areasVigentes() : [];

        $query->where(fn (Builder $q) => $q
            ->where('responsable_id', $user->id)
            ->when($areas !== [], fn (Builder $q) => $q->orWhereIn('area_principal_id', $areas)));
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_principal_id');
    }

    /** @return BelongsTo<TipoTramite, $this> */
    public function tipoTramite(): BelongsTo
    {
        return $this->belongsTo(TipoTramite::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /** @return HasMany<Movimiento, $this> */
    public function movimientos(): HasMany
    {
        return $this->hasMany(Movimiento::class);
    }

    /** @return BelongsTo<Emisor, $this> */
    public function emisor(): BelongsTo
    {
        return $this->belongsTo(Emisor::class);
    }

    /** @return BelongsTo<TipoDocumento, $this> */
    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class);
    }

    /** @return HasMany<Correo, $this> */
    public function correos(): HasMany
    {
        return $this->hasMany(Correo::class)->orderBy('fecha');
    }

    /** @return HasMany<Documento, $this> */
    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class)->orderBy('id');
    }
}
