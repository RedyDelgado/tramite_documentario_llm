<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/** Un mensaje ya leído de un buzón: no se vuelve a descargar aunque en Gmail no lleve la etiqueta. */
#[Table('correos_leidos')]
#[Fillable(['buzon', 'uid'])]
class CorreoLeido extends Model
{
    public const UPDATED_AT = null;
}
