<?php

namespace App\Services;

use App\Enums\EstadoExpediente;
use App\Exceptions\ReglaDeNegocio;
use App\Jobs\ClasificarExpediente;
use App\Models\Expediente;
use App\Models\TipoTramite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Transiciones del registro de un expediente; toda escritura queda auditada. */
class ExpedienteService
{
    public function __construct(
        private readonly SecuenciaService $secuencias,
        private readonly AuditoriaService $auditoria,
        private readonly PlazoService $plazos,
    ) {}

    /** Asigna el tipo de trámite con una copia del plazo vigente: cambiar después la configuración no lo altera (5.1). */
    public function asignarTipo(Expediente $expediente, TipoTramite $tipo): Expediente
    {
        return DB::transaction(function () use ($expediente, $tipo) {
            $expediente = Expediente::lockForUpdate()->findOrFail($expediente->id);
            $plazo = $this->plazos->calcular($tipo, $expediente->area_principal_id, $expediente->fecha_ingreso);

            $expediente->forceFill([
                'tipo_tramite_id' => $tipo->id,
                'plazo_dias_aplicado' => $plazo['plazo_dias'],
                'fecha_limite' => $plazo['fecha_limite']?->toDateString(),
            ])->save();
            $this->auditoria->registrarCambios('expediente.tipo_asignado', $expediente);

            return $expediente;
        });
    }

    /**
     * Confirma como trámite y asigna el número de registro del año en curso (6.2).
     *
     * @param  array{emisor_id?: ?int, tipo_documento_id?: ?int}  $datos  datos del documento (6.1) que se fijan al registrar
     * @param  int|null  $numeroPapel  trámite en curso que conserva su N° del registro en papel, sin consumir el correlativo
     *
     * @throws ReglaDeNegocio si el estado no admite confirmación.
     */
    public function confirmar(Expediente $expediente, array $datos = [], ?int $numeroPapel = null): Expediente
    {
        return DB::transaction(function () use ($expediente, $datos, $numeroPapel) {
            // Bloqueo de la fila: dos confirmaciones simultáneas no pueden numerar dos veces.
            $expediente = Expediente::lockForUpdate()->findOrFail($expediente->id);

            if (! $expediente->estado->puedeConfirmarse()) {
                throw new ReglaDeNegocio("Un expediente {$expediente->estado->etiqueta()} no puede confirmarse como trámite.");
            }

            $estadoPrevio = $expediente->estado;
            $anio = now()->year;
            $documento = array_intersect_key($datos, array_flip(['emisor_id', 'tipo_documento_id']));
            $expediente->forceFill([
                ...$documento,
                'anio' => $anio,
                'secuencia' => $numeroPapel ?? $this->secuencias->siguiente('registro', $anio),
                'estado' => EstadoExpediente::Registrado,
                'registrado_at' => now(),
                'registrado_por' => Auth::id(),
            ])->save();

            $this->auditoria->registrar('registro.asignado', $expediente,
                antes: ['estado' => $estadoPrevio->value],
                despues: ['estado' => EstadoExpediente::Registrado->value, 'numero' => $expediente->numero_registro, 'codigo' => $expediente->codigo, ...$documento, ...($numeroPapel ? ['del_registro_en_papel' => true] : [])],
            );
            // La IA propone en segundo plano; el registro no la espera (10).
            ClasificarExpediente::dispatch($expediente->id)->afterCommit();

            return $expediente;
        });
    }

    /**
     * Archiva como no trámite: sin número, sin semáforo, recuperable (7.3.2).
     *
     * @throws ReglaDeNegocio si ya fue registrado.
     */
    public function marcarNoTramite(Expediente $expediente): Expediente
    {
        return DB::transaction(function () use ($expediente) {
            $expediente = Expediente::lockForUpdate()->findOrFail($expediente->id);

            if (! in_array($expediente->estado, [EstadoExpediente::PorRevisar, EstadoExpediente::Historico], true)) {
                throw new ReglaDeNegocio('Solo un expediente por revisar o histórico puede marcarse como no trámite; uno registrado se anula.');
            }

            $estadoPrevio = $expediente->estado;
            $expediente->update(['estado' => EstadoExpediente::NoTramite]);
            $this->auditoria->registrar('expediente.no_tramite', $expediente,
                antes: ['estado' => $estadoPrevio->value], despues: ['estado' => EstadoExpediente::NoTramite->value]);

            return $expediente;
        });
    }

    /** Devuelve a revisión un correo que se archivó como no trámite por error. */
    public function devolverARevision(Expediente $expediente): Expediente
    {
        return DB::transaction(function () use ($expediente) {
            $expediente = Expediente::lockForUpdate()->findOrFail($expediente->id);

            if ($expediente->estado !== EstadoExpediente::NoTramite) {
                throw new ReglaDeNegocio('Solo un expediente archivado como no trámite puede volver a revisión.');
            }

            $expediente->update(['estado' => EstadoExpediente::PorRevisar]);
            $this->auditoria->registrar('expediente.devuelto_a_revision', $expediente,
                antes: ['estado' => EstadoExpediente::NoTramite->value], despues: ['estado' => EstadoExpediente::PorRevisar->value]);

            return $expediente;
        });
    }

    /**
     * Anula un registro erróneo: conserva su número, que nunca se reutiliza (6.2).
     *
     * @throws ReglaDeNegocio si no tiene número o ya está anulado.
     */
    public function anular(Expediente $expediente, string $motivo): Expediente
    {
        return DB::transaction(function () use ($expediente, $motivo) {
            $expediente = Expediente::lockForUpdate()->findOrFail($expediente->id);

            if ($expediente->secuencia === null) {
                throw new ReglaDeNegocio('Solo se anula un expediente con número de registro; si no es trámite, márcalo como tal.');
            }
            if ($expediente->estado === EstadoExpediente::Anulado) {
                throw new ReglaDeNegocio("El expediente {$expediente->numero_registro} ya está anulado.");
            }

            $estadoPrevio = $expediente->estado;
            $expediente->forceFill(['estado' => EstadoExpediente::Anulado, 'motivo_anulacion' => $motivo])->save();
            $this->auditoria->registrar('registro.anulado', $expediente,
                antes: ['estado' => $estadoPrevio->value],
                despues: ['estado' => EstadoExpediente::Anulado->value, 'numero' => $expediente->numero_registro, 'motivo' => $motivo],
            );

            return $expediente;
        });
    }
}
