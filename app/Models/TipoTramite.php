<?php

namespace App\Models;

use App\Policies\ConfiguracionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

/** Naturaleza del trámite; lleva el plazo por defecto (6.1). */
#[UsePolicy(ConfiguracionPolicy::class)]
#[Table('tipos_tramite')]
#[Fillable(['nombre', 'descripcion', 'plazo_dias', 'tipo_dias', 'aprueba_cierre', 'activo'])]
class TipoTramite extends Model
{
    public const TIPOS_DIAS = ['habiles' => 'Días hábiles', 'calendario' => 'Días calendario'];

    public const APRUEBA_CIERRE = ['director' => 'Director', 'coordinador' => 'Coordinador del área'];

    protected $attributes = ['tipo_dias' => 'habiles', 'activo' => true];

    protected function casts(): array
    {
        return ['plazo_dias' => 'integer', 'activo' => 'boolean'];
    }

    /** @return list<array{value: int, label: string}> */
    public static function opciones(): array
    {
        return self::where('activo', true)->orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (TipoTramite $t) => ['value' => $t->id, 'label' => $t->nombre])
            ->all();
    }
}
