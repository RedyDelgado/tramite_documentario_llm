<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Paso de la atención de un expediente; solo se inserta, nunca se edita (lo escribe AtencionService). */
#[Fillable(['expediente_id', 'tipo', 'user_id', 'de_area_id', 'a_area_id', 'a_user_id', 'instruccion', 'nota', 'fecha_limite'])]
class Movimiento extends Model
{
    public const UPDATED_AT = null;

    public const TIPOS = [
        'derivacion' => 'Derivó',
        'en_atencion' => 'Tomó en atención',
        'toma_conocimiento' => 'Tomó conocimiento',
        'comentario' => 'Comentó',
        'cierre_solicitado' => 'Solicitó el cierre',
        'cierre_rechazado' => 'Rechazó el cierre',
        'cierre_aprobado' => 'Cerró el expediente',
        'original_movido' => 'Movió el original',
        'respuesta' => 'Envió la respuesta',
    ];

    protected function casts(): array
    {
        return ['fecha_limite' => 'date:Y-m-d', 'created_at' => 'datetime'];
    }

    /** @return BelongsTo<Expediente, $this> */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Area, $this> */
    public function aArea(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'a_area_id');
    }

    /** @return BelongsTo<User, $this> */
    public function aUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'a_user_id');
    }
}
