<?php

namespace App\Models;

use App\Policies\ConfiguracionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

/** Forma del documento (oficio, carta, informe…); distinto del tipo de trámite, que lleva el plazo (6.1). */
#[UsePolicy(ConfiguracionPolicy::class)]
#[Table('tipos_documento')]
#[Fillable(['nombre', 'formato_numero', 'aprueba_salida', 'activo'])]
class TipoDocumento extends Model
{
    public const APRUEBA_SALIDA = ['director' => 'Director', 'coordinador' => 'Coordinador del área que emite'];

    protected $attributes = ['activo' => true, 'formato_numero' => '{TIPO} N.º {NUMERO}-{ANIO}-{AREA}', 'aprueba_salida' => 'director'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /** @return list<array{value: int, label: string}> */
    public static function opciones(): array
    {
        return self::where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (self $t) => ['value' => $t->id, 'label' => $t->nombre])
            ->all();
    }
}
