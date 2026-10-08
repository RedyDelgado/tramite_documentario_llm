<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/** Una cuenta de Google conectada al buzón central (7.1); la principal es la que envía los documentos. */
#[Table('buzones')]
#[Fillable(['cuenta', 'refresh_token', 'principal'])]
#[Hidden(['refresh_token'])]
class Buzon extends Model
{
    protected function casts(): array
    {
        return ['refresh_token' => 'encrypted', 'principal' => 'boolean', 'ultima_lectura' => 'array'];
    }
}
