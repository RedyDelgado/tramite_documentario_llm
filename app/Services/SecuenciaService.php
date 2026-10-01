<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use LogicException;

/** Correlativos sin saltos ni duplicados (6.2). */
class SecuenciaService
{
    /**
     * Toma el siguiente número; debe llamarse dentro de la transacción que lo consume,
     * para que un rollback devuelva el número y no deje saltos.
     *
     * @throws LogicException si no hay transacción abierta.
     */
    public function siguiente(string $clave, int $anio): int
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('SecuenciaService::siguiente exige una transacción abierta.');
        }

        $inicio = (int) (config("tramite.secuencias_inicio.{$clave}.{$anio}") ?? 1);

        DB::table('secuencias')->insertOrIgnore([
            'clave' => $clave, 'anio' => $anio, 'ultimo' => $inicio - 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // UPDATE ... RETURNING bloquea la fila hasta el commit: dos altas simultáneas se ordenan aquí.
        return (int) DB::selectOne(
            'UPDATE secuencias SET ultimo = ultimo + 1, updated_at = now() WHERE clave = ? AND anio = ? RETURNING ultimo',
            [$clave, $anio],
        )->ultimo;
    }
}
