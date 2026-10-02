<?php

namespace App\Models;

use App\Policies\ConfiguracionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

/** Instrucción de derivación que se elige de una lista en vez de escribirla (7.3.5). */
#[UsePolicy(ConfiguracionPolicy::class)]
#[Table('instrucciones_frecuentes')]
#[Fillable(['texto', 'orden', 'activa'])]
class InstruccionFrecuente extends Model
{
    protected $attributes = ['orden' => 0, 'activa' => true];

    protected function casts(): array
    {
        return ['orden' => 'integer', 'activa' => 'boolean'];
    }

    /** @return list<array{value: int, label: string}> */
    public static function opciones(): array
    {
        return self::where('activa', true)->orderBy('orden')->orderBy('texto')->get(['id', 'texto'])
            ->map(fn (self $i) => ['value' => $i->id, 'label' => $i->texto])
            ->all();
    }
}
