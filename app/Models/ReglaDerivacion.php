<?php

namespace App\Models;

use App\Policies\ConfiguracionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Sugiere área (y responsable) de un expediente (5.1). La condición combina tipo de trámite,
 * palabras clave en el asunto y remitentes (correo exacto o dominio): deben cumplirse todas las presentes.
 */
#[UsePolicy(ConfiguracionPolicy::class)]
#[Table('reglas_derivacion')]
#[Fillable(['nombre', 'tipo_tramite_id', 'condicion', 'area_destino_id', 'responsable_id', 'prioridad', 'activa'])]
class ReglaDerivacion extends Model
{
    protected $attributes = ['prioridad' => 100, 'activa' => true];

    protected function casts(): array
    {
        return ['condicion' => 'array', 'prioridad' => 'integer', 'activa' => 'boolean'];
    }

    /** Primera regla activa, por prioridad, que aplica al expediente. */
    public static function primeraQueAplica(Expediente $expediente): ?self
    {
        return self::where('activa', true)->orderBy('prioridad')->orderBy('id')->get()->first(fn (self $r) => $r->aplica($expediente));
    }

    public function aplica(Expediente $expediente): bool
    {
        $palabras = $this->condicion['palabras_clave'] ?? [];
        $remitentes = $this->condicion['remitentes'] ?? [];
        $asunto = mb_strtolower((string) $expediente->asunto);
        $email = mb_strtolower((string) $expediente->remitente_email);
        $dominio = Str::after($email, '@');

        return ($this->tipo_tramite_id === null || $this->tipo_tramite_id === $expediente->tipo_tramite_id)
            && ($palabras === [] || collect($palabras)->contains(fn ($p) => str_contains($asunto, mb_strtolower($p))))
            && ($remitentes === [] || collect($remitentes)->map(fn ($r) => mb_strtolower($r))
                ->contains(fn ($r) => str_contains($r, '@') ? $r === $email : ($dominio === $r || str_ends_with($dominio, ".{$r}"))));
    }

    /** @return BelongsTo<TipoTramite, $this> */
    public function tipoTramite(): BelongsTo
    {
        return $this->belongsTo(TipoTramite::class);
    }

    /** @return BelongsTo<Area, $this> */
    public function areaDestino(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_destino_id');
    }

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }
}
