<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Solo lectura desde la aplicación: se escribe únicamente con AuditoriaService. */
class Auditoria extends Model
{
    protected $table = 'auditoria';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'valor_anterior' => 'array',
            'valor_nuevo' => 'array',
        ];
    }

    /** @param Builder<Auditoria> $query */
    public function scopeDe(Builder $query, Model $modelo): void
    {
        $query->where('entidad', $modelo->getMorphClass())->where('entidad_id', (string) $modelo->getKey());
    }

    /**
     * Hash de un registro encadenado al anterior; los arreglos se ordenan por clave para que
     * el valor leído de jsonb (que reordena claves) produzca el mismo hash.
     *
     * @param  array<string, mixed>  $campos
     */
    public static function calcularHash(array $campos, ?string $hashAnterior): string
    {
        return hash('sha256', ($hashAnterior ?? '').json_encode(self::canonico($campos), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
    }

    private static function canonico(mixed $valor): mixed
    {
        if (! is_array($valor)) {
            return $valor;
        }
        if (! array_is_list($valor)) {
            ksort($valor);
        }

        return array_map(self::canonico(...), $valor);
    }
}
