<?php

namespace App\Models;

use App\Enums\EstadoExpediente;
use App\Enums\OrigenExpediente;
use Database\Factories\ExpedienteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'origen', 'estado', 'asunto', 'remitente_nombre', 'remitente_email', 'remitente_por_confirmar',
    'fecha_ingreso', 'area_principal_id', 'responsable_id',
])]
class Expediente extends Model
{
    /** @use HasFactory<ExpedienteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'estado' => EstadoExpediente::class,
            'origen' => OrigenExpediente::class,
            'fecha_ingreso' => 'datetime',
            'registrado_at' => 'datetime',
            'remitente_por_confirmar' => 'boolean',
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

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }
}
