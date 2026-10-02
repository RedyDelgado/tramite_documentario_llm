<?php

namespace App\Models;

use App\Policies\ConfiguracionPolicy;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;

/** Parámetros clave/valor editables desde el panel (5.1); sin fila, rige el valor por defecto. */
#[UsePolicy(ConfiguracionPolicy::class)]
#[Table('configuraciones', key: 'clave', keyType: 'string', incrementing: false)]
#[Fillable(['clave', 'valor'])]
class Configuracion extends Model
{
    /** Semáforo (8): amarillo cuando queda menos de este % del plazo o pasan estos días sin movimiento. */
    public const DEFECTOS = [
        'semaforo.porcentaje_amarillo' => 30,
        'semaforo.dias_sin_movimiento' => 5,
        // IA (10): en sombra propone y se compara, sin mostrarse ni ejecutar nada; los umbrales se ajustan con sus datos.
        'ia.modo' => 'sombra',
        'ia.umbral_sugerencia' => 0.60,
        'ia.umbral_alta' => 0.90,
    ];

    protected function casts(): array
    {
        return ['valor' => 'json'];
    }

    public static function valor(string $clave): mixed
    {
        return self::find($clave)?->valor ?? self::DEFECTOS[$clave];
    }

    /** @return array<string, mixed> */
    public static function todas(): array
    {
        return self::pluck('valor', 'clave')->all() + self::DEFECTOS;
    }
}
