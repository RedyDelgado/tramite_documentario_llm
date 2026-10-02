<?php

namespace App\Models;

use App\Policies\IaPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Corrección humana a la IA en un campo (`area` o `tipo`); solo las validadas sirven para reentrenar (10). */
#[UsePolicy(IaPolicy::class)]
#[Table('correcciones_pendientes')]
#[Fillable(['clasificacion_id', 'campo', 'valor_ia', 'valor_humano', 'usuario_id'])]
class CorreccionPendiente extends Model
{
    protected function casts(): array
    {
        return ['validada_at' => 'datetime', 'valor_ia' => 'integer', 'valor_humano' => 'integer'];
    }

    /** @return BelongsTo<ClasificacionIa, $this> */
    public function clasificacion(): BelongsTo
    {
        return $this->belongsTo(ClasificacionIa::class, 'clasificacion_id');
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
