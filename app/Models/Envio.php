<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Correo individual de un documento saliente a un destinatario (7.3.4): pendiente, enviado, rebotado o fallido. */
#[Fillable(['documento_saliente_id', 'email', 'nombre'])]
class Envio extends Model
{
    protected $attributes = ['estado' => 'pendiente'];

    protected function casts(): array
    {
        return ['enviado_at' => 'datetime', 'rebotado_at' => 'datetime'];
    }

    /** @return BelongsTo<DocumentoSaliente, $this> */
    public function saliente(): BelongsTo
    {
        return $this->belongsTo(DocumentoSaliente::class, 'documento_saliente_id');
    }
}
