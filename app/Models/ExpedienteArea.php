<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un área que atiende el expediente, con su responsable, su plazo y su estado; lo escribe AtencionService. */
#[Fillable(['expediente_id', 'area_id', 'responsable_id', 'plazo_dias_aplicado', 'fecha_limite', 'estado', 'atendido_at', 'atendido_por', 'nota_atencion'])]
class ExpedienteArea extends Model
{
    protected $table = 'expediente_areas';

    public const ESTADOS = ['derivado' => 'Por tomar', 'en_atencion' => 'En atención', 'atendido' => 'Atendido'];

    protected function casts(): array
    {
        return ['fecha_limite' => 'date:Y-m-d', 'atendido_at' => 'datetime', 'plazo_dias_aplicado' => 'integer'];
    }

    public function pendiente(): bool
    {
        return $this->estado !== 'atendido';
    }

    /** @return BelongsTo<Expediente, $this> */
    public function expediente(): BelongsTo
    {
        return $this->belongsTo(Expediente::class);
    }

    /** @return BelongsTo<Area, $this> */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    /** @return BelongsTo<User, $this> */
    public function atendidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atendido_por');
    }
}
