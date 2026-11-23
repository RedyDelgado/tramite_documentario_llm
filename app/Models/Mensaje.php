<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['conversacion_id', 'user_id', 'texto'])]
class Mensaje extends Model
{
    protected $table = 'mensajes';

    /** @return BelongsTo<Conversacion, $this> */
    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(Conversacion::class);
    }

    /** @return BelongsTo<User, $this> */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
