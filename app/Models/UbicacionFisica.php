<?php

namespace App\Models;

use App\Policies\ConfiguracionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

/** Archivador, caja o estante donde se guarda un original en papel (7.3.1). */
#[UsePolicy(ConfiguracionPolicy::class)]
#[Table('ubicaciones_fisicas')]
#[Fillable(['nombre', 'descripcion', 'activa'])]
class UbicacionFisica extends Model
{
    protected $attributes = ['activa' => true];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }

    /** @return list<array{value: int, label: string}> */
    public static function opciones(): array
    {
        return self::where('activa', true)->orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (self $u) => ['value' => $u->id, 'label' => $u->nombre])
            ->all();
    }
}
