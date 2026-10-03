<?php

namespace App\Services;

use App\Models\Area;
use App\Models\DocumentoSaliente;
use App\Models\Expediente;
use App\Models\TipoDocumento;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Numeración desde el panel (pendiente 9): el administrador fija con qué número continúa cada correlativo del año.
 * Nunca hacia atrás de lo ya usado: un número no se repite (6.2).
 */
class NumeracionService
{
    public const REGISTRO = 'registro';

    public function __construct(
        private readonly SecuenciaService $secuencias,
        private readonly SalienteService $salientes,
        private readonly AuditoriaService $auditoria,
    ) {}

    /**
     * El registro y los correlativos de documentos emitidos que ya existen en el año, con su próximo número formateado.
     *
     * @return list<array{clave: string, nombre: string, ultimo_usado: ?int, siguiente: int, ejemplo: string, tipo_documento_id: ?int, area_id: ?int}>
     */
    public function lista(int $anio): array
    {
        $filas = [$this->fila(self::REGISTRO, $anio)];
        $claves = DB::table('secuencias')->where('anio', $anio)->where('clave', 'like', 'saliente:%')->orderBy('clave')->pluck('clave');

        return [...$filas, ...$claves->map(fn (string $clave) => $this->fila($clave, $anio))->filter()->values()->all()];
    }

    /**
     * @param  array{tipo: string, anio: int, siguiente: int, tipo_documento_id?: ?int, area_id?: ?int}  $datos
     *
     * @throws ValidationException si el número ya se usó
     */
    public function ajustar(array $datos): void
    {
        $clave = $datos['tipo'] === self::REGISTRO ? self::REGISTRO : "saliente:{$datos['tipo_documento_id']}:{$datos['area_id']}";
        $anio = (int) $datos['anio'];
        $siguiente = (int) $datos['siguiente'];

        DB::transaction(function () use ($clave, $anio, $siguiente) {
            // Fila bloqueada antes de mirar lo usado: nadie numera entre la comprobación y el ajuste.
            DB::table('secuencias')->where('clave', $clave)->where('anio', $anio)->lockForUpdate()->first();
            $usado = $this->ultimoUsado($clave, $anio);
            if ($usado !== null && $siguiente <= $usado) {
                throw ValidationException::withMessages(['siguiente' => "Ya se usó el número {$usado} en {$anio}: el siguiente debe ser mayor."]);
            }

            $cambio = $this->secuencias->fijarSiguiente($clave, $anio, $siguiente);
            $this->auditoria->registrar('numeracion.ajustada', 'secuencia', "{$clave}:{$anio}",
                antes: ['siguiente' => $cambio['antes']], despues: ['siguiente' => $cambio['despues']]);
        });
    }

    /** El número más alto ya puesto en la serie (también los trámites en curso que conservaron el suyo). */
    private function ultimoUsado(string $clave, int $anio): ?int
    {
        if ($clave === self::REGISTRO) {
            return Expediente::where('anio', $anio)->max('secuencia');
        }
        [, $tipo, $area] = explode(':', $clave);

        return DocumentoSaliente::where('tipo_documento_id', $tipo)->where('area_id', $area)->where('anio', $anio)->max('secuencia');
    }

    /** @return array{clave: string, nombre: string, ultimo_usado: ?int, siguiente: int, ejemplo: string, tipo_documento_id: ?int, area_id: ?int}|null */
    private function fila(string $clave, int $anio): ?array
    {
        $siguiente = $this->secuencias->proximo($clave, $anio);

        if ($clave === self::REGISTRO) {
            return [
                'clave' => $clave, 'nombre' => 'Registro de documentos recibidos', 'ultimo_usado' => $this->ultimoUsado($clave, $anio),
                'siguiente' => $siguiente, 'ejemplo' => sprintf(config('tramite.registro.formato'), $siguiente), 'tipo_documento_id' => null, 'area_id' => null,
            ];
        }

        [, $tipoId, $areaId] = explode(':', $clave);
        $tipo = TipoDocumento::find($tipoId);
        $area = Area::find($areaId);
        if (! $tipo || ! $area) {
            return null;
        }

        return [
            'clave' => $clave, 'nombre' => "{$tipo->nombre} · {$area->nombre}", 'ultimo_usado' => $this->ultimoUsado($clave, $anio),
            'siguiente' => $siguiente, 'ejemplo' => $this->salientes->formatear($tipo, $area, $siguiente, $anio),
            'tipo_documento_id' => $tipo->id, 'area_id' => $area->id,
        ];
    }
}
