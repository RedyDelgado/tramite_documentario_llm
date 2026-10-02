<?php

namespace App\Models;

use App\Enums\Semaforo;
use App\Policies\SalientePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Documento emitido por la institución (7.3.4): borrador → revisión → aprobado (numerado) → enviado. */
#[UsePolicy(SalientePolicy::class)]
#[Table('documentos_salientes')]
#[Fillable([
    'expediente_id', 'plantilla_id', 'tipo_documento_id', 'area_id', 'asunto', 'cuerpo', 'destinatarios', 'es_respuesta',
    'requiere_respuesta', 'plazo_respuesta_dias', 'esperar_firma', 'creado_por',
])]
class DocumentoSaliente extends Model
{
    public const ESTADOS = [
        'borrador' => 'Borrador',
        'en_revision' => 'En revisión',
        'aprobado' => 'Aprobado',
        'enviado' => 'Enviado',
    ];

    protected $attributes = ['estado' => 'borrador', 'es_respuesta' => false, 'requiere_respuesta' => false, 'esperar_firma' => false];

    protected function casts(): array
    {
        return [
            'destinatarios' => 'array',
            'es_respuesta' => 'boolean',
            'requiere_respuesta' => 'boolean',
            'esperar_firma' => 'boolean',
            'fecha_limite_respuesta' => 'date:Y-m-d',
            'aprobado_at' => 'datetime',
            'enviado_at' => 'datetime',
            'respondido_at' => 'datetime',
            'anio' => 'integer',
            'secuencia' => 'integer',
        ];
    }

    /**
     * Semáforo propio de lo emitido que exige respuesta (7.3.4): rojo si venció sin respuesta, amarillo en el último tramo.
     */
    public function semaforo(?CarbonImmutable $hoy = null): ?Semaforo
    {
        if (! $this->requiere_respuesta || ! $this->fecha_limite_respuesta || ! $this->enviado_at || $this->respondido_at) {
            return null;
        }
        $hoy ??= CarbonImmutable::today();
        $limite = CarbonImmutable::parse($this->fecha_limite_respuesta->toDateString());
        if ($hoy->gt($limite)) {
            return Semaforo::Rojo;
        }
        $inicio = CarbonImmutable::parse($this->enviado_at->setTimezone(config('app.timezone'))->toDateString());
        $total = max(1, $inicio->diffInDays($limite));

        return $hoy->diffInDays($limite) / $total * 100 < Configuracion::valor('semaforo.porcentaje_amarillo') ? Semaforo::Amarillo : Semaforo::Verde;
    }

    /** @return BelongsTo<Expediente, $this> */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    /** @return BelongsTo<TipoDocumento, $this> */
    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(TipoDocumento::class);
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** @return BelongsTo<User, $this> */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    /** @return BelongsTo<User, $this> */
    public function aprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    /** @return HasMany<Envio, $this> */
    public function envios(): HasMany
    {
        return $this->hasMany(Envio::class);
    }
}
