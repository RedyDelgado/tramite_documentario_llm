<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Correlativos sin saltos ni duplicados (6.2). El número con que arranca cada uno lo fija el administrador desde el
 * panel (Numeración); sin ajuste, rige `tramite.secuencias_inicio` y, si no, el 1.
 */
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

        $inicio = $this->inicioPorDefecto($clave, $anio);

        DB::table('secuencias')->insertOrIgnore([
            'clave' => $clave, 'anio' => $anio, 'inicio' => $inicio, 'ultimo' => $inicio - 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        // UPDATE ... RETURNING bloquea la fila hasta el commit: dos altas simultáneas se ordenan aquí.
        return (int) DB::selectOne(
            'UPDATE secuencias SET ultimo = ultimo + 1, updated_at = now() WHERE clave = ? AND anio = ? RETURNING ultimo',
            [$clave, $anio],
        )->ultimo;
    }

    /** Primer número que emite el sistema en el año (lo anterior es del registro en papel). */
    public function inicio(string $clave, int $anio): int
    {
        return (int) (DB::table('secuencias')->where('clave', $clave)->where('anio', $anio)->value('inicio') ?? $this->inicioPorDefecto($clave, $anio));
    }

    /** El número que saldrá a continuación, sin tomarlo. */
    public function proximo(string $clave, int $anio): int
    {
        $ultimo = DB::table('secuencias')->where('clave', $clave)->where('anio', $anio)->value('ultimo');

        return $ultimo === null ? $this->inicioPorDefecto($clave, $anio) : (int) $ultimo + 1;
    }

    /**
     * El siguiente número será $siguiente. Si el sistema aún no emitió ninguno, también pasa a ser el inicio del año.
     * Quien llama comprueba que sea mayor que el último usado; aquí se bloquea la fila para que nadie numere entretanto.
     *
     * @return array{antes: int, despues: int}
     */
    public function fijarSiguiente(string $clave, int $anio, int $siguiente): array
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('SecuenciaService::fijarSiguiente exige una transacción abierta.');
        }

        $fila = DB::table('secuencias')->where('clave', $clave)->where('anio', $anio)->lockForUpdate()->first();
        $antes = $fila ? (int) $fila->ultimo + 1 : $this->inicioPorDefecto($clave, $anio);
        $sinEmitir = ! $fila || (int) $fila->ultimo < (int) ($fila->inicio ?? 1);

        DB::table('secuencias')->updateOrInsert(
            ['clave' => $clave, 'anio' => $anio],
            [
                'ultimo' => $siguiente - 1,
                ...($sinEmitir ? ['inicio' => $siguiente] : []),
                'updated_at' => now(),
                ...($fila ? [] : ['created_at' => now()]),
            ],
        );

        return ['antes' => $antes, 'despues' => $siguiente];
    }

    private function inicioPorDefecto(string $clave, int $anio): int
    {
        return (int) (config("tramite.secuencias_inicio.{$clave}.{$anio}") ?? 1);
    }
}
