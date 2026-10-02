<?php

namespace App\Services;

use App\Enums\EstadoExpediente;
use App\Exceptions\ReglaDeNegocio;
use App\Models\DocumentoSaliente;
use App\Models\Expediente;
use App\Models\Movimiento;
use App\Models\TipoTramite;
use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Atención de un expediente (7, 8): cada paso deja un movimiento y queda auditado; el semáforo se recalcula al guardar. */
class AtencionService
{
    private const ABIERTOS = [EstadoExpediente::Derivado, EstadoExpediente::EnAtencion];

    // Campos cuyo valor anterior y nuevo quedan en la auditoría de cada paso.
    private const AUDITADOS = [
        'estado', 'area_principal_id', 'responsable_id', 'tipo_tramite_id', 'plazo_dias_aplicado',
        'fecha_limite', 'requiere_respuesta', 'cierre_solicitado_at', 'atendido_at',
    ];

    public function __construct(
        private readonly AuditoriaService $auditoria,
        private readonly PlazoService $plazos,
        private readonly ClasificacionService $clasificacion,
    ) {}

    /**
     * Deriva o reasigna. El plazo se copia del tipo al asignarlo (5.1); una fecha explícita del documento manda (8).
     *
     * @param  array{tipo_tramite_id: int, area_id: int, responsable_id?: ?int, requiere_respuesta: bool, instruccion?: ?string, nota?: ?string, fecha_limite?: ?string}  $datos
     */
    public function derivar(Expediente $expediente, array $datos): Expediente
    {
        $expediente = $this->paso($expediente, [EstadoExpediente::Registrado, ...self::ABIERTOS], 'derivar', function (Expediente $e) use ($datos) {
            $tipo = TipoTramite::findOrFail($datos['tipo_tramite_id']);
            $areaId = (int) $datos['area_id'];
            $cambios = [
                'tipo_tramite_id' => $tipo->id,
                'area_principal_id' => $areaId,
                'responsable_id' => $datos['responsable_id'] ?? null,
                'requiere_respuesta' => $datos['requiere_respuesta'],
                'estado' => EstadoExpediente::Derivado,
                'cierre_solicitado_at' => null,
            ];

            if ($datos['fecha_limite'] ?? null) {
                $cambios['fecha_limite'] = $datos['fecha_limite'];
            } elseif ($e->tipo_tramite_id !== $tipo->id || $e->area_principal_id !== $areaId || $e->plazo_dias_aplicado === null) {
                // Tipo o área nuevos: rige su plazo vigente; reasignar sin cambios conserva la fecha ya fijada.
                $plazo = $this->plazos->calcular($tipo, $areaId, $e->fecha_ingreso);
                $cambios += ['plazo_dias_aplicado' => $plazo['plazo_dias'], 'fecha_limite' => $plazo['fecha_limite']?->toDateString()];
            }

            $deArea = $e->area_principal_id;
            $e->forceFill($cambios);

            return [
                'accion' => 'expediente.derivado',
                'movimiento' => [
                    'tipo' => 'derivacion',
                    'de_area_id' => $deArea,
                    'a_area_id' => $areaId,
                    'a_user_id' => $datos['responsable_id'] ?? null,
                    'instruccion' => $datos['instruccion'] ?? null,
                    'nota' => $datos['nota'] ?? null,
                    'fecha_limite' => $e->fecha_limite?->toDateString(),
                ],
            ];
        });
        // La derivación es la decisión humana con la que se mide la IA (10).
        $this->clasificacion->registrarDecision($expediente, Auth::user());

        return $expediente;
    }

    /** Quien atiende lo toma; si es solo para conocimiento y sin plazo, tomarlo ya lo deja atendido (8). */
    public function tomar(Expediente $expediente): Expediente
    {
        return $this->paso($expediente, [EstadoExpediente::Derivado], 'tomar', function (Expediente $e) {
            $soloConocimiento = ! $e->requiere_respuesta && $e->fecha_limite === null;
            $e->forceFill($soloConocimiento
                ? ['estado' => EstadoExpediente::Atendido, 'atendido_at' => now()]
                : ['estado' => EstadoExpediente::EnAtencion]);

            return ['accion' => 'expediente.'.($soloConocimiento ? 'conocimiento_tomado' : 'en_atencion'), 'movimiento' => ['tipo' => $soloConocimiento ? 'toma_conocimiento' : 'en_atencion']];
        });
    }

    /** Enviada la respuesta vinculada, el expediente queda atendido sin que nadie lo marque (7.3.5, punto 7). */
    public function atenderConRespuesta(Expediente $expediente, DocumentoSaliente $respuesta): Expediente
    {
        return $this->paso($expediente, [EstadoExpediente::Registrado, ...self::ABIERTOS], 'atender', function (Expediente $e) use ($respuesta) {
            $e->forceFill(['estado' => EstadoExpediente::Atendido, 'atendido_at' => now(), 'cierre_solicitado_at' => null]);

            return ['accion' => 'expediente.respondido', 'movimiento' => ['tipo' => 'respuesta', 'nota' => "Respuesta enviada: {$respuesta->numero}"]];
        });
    }

    public function comentar(Expediente $expediente, string $nota): Expediente
    {
        return $this->paso($expediente, [EstadoExpediente::Registrado, ...self::ABIERTOS], 'comentar', fn () => [
            'accion' => 'expediente.comentado',
            'movimiento' => ['tipo' => 'comentario', 'nota' => $nota],
        ]);
    }

    /** Sin aprobación configurada en el tipo, solicitar el cierre ya cierra (5). */
    public function solicitarCierre(Expediente $expediente, ?string $nota): Expediente
    {
        return $this->paso($expediente, self::ABIERTOS, 'solicitar cierre', function (Expediente $e) use ($nota) {
            if ($e->cierre_solicitado_at) {
                throw new ReglaDeNegocio('El cierre ya está solicitado; falta su aprobación.');
            }
            if ($e->tipoTramite?->aprueba_cierre) {
                $e->forceFill(['cierre_solicitado_at' => now()]);

                return ['accion' => 'expediente.cierre_solicitado', 'movimiento' => ['tipo' => 'cierre_solicitado', 'nota' => $nota]];
            }
            $e->forceFill(['estado' => EstadoExpediente::Cerrado, 'atendido_at' => now()]);

            return ['accion' => 'expediente.cerrado', 'movimiento' => ['tipo' => 'cierre_aprobado', 'nota' => $nota]];
        });
    }

    public function resolverCierre(Expediente $expediente, bool $aprobar, ?string $nota): Expediente
    {
        return $this->paso($expediente, self::ABIERTOS, 'resolver el cierre', function (Expediente $e) use ($aprobar, $nota) {
            if (! $e->cierre_solicitado_at) {
                throw new ReglaDeNegocio('No hay un cierre pendiente de aprobación.');
            }
            $e->forceFill($aprobar
                ? ['estado' => EstadoExpediente::Cerrado, 'atendido_at' => now(), 'cierre_solicitado_at' => null]
                : ['cierre_solicitado_at' => null]);

            return [
                'accion' => $aprobar ? 'expediente.cerrado' : 'expediente.cierre_rechazado',
                'movimiento' => ['tipo' => $aprobar ? 'cierre_aprobado' : 'cierre_rechazado', 'nota' => $nota],
            ];
        });
    }

    /**
     * Esqueleto común: bloquea la fila, valida el estado, aplica el cambio y guarda movimiento y auditoría.
     *
     * @param  list<EstadoExpediente>  $estados
     * @param  Closure(Expediente): array{accion: string, movimiento: array<string, mixed>}  $cambio
     */
    private function paso(Expediente $expediente, array $estados, string $verbo, Closure $cambio): Expediente
    {
        $expediente = DB::transaction(function () use ($expediente, $estados, $verbo, $cambio) {
            $e = Expediente::lockForUpdate()->findOrFail($expediente->id);
            if (! in_array($e->estado, $estados, true)) {
                throw new ReglaDeNegocio("Un expediente {$e->estado->etiqueta()} no se puede {$verbo}.");
            }

            $antes = $this->serializar($e->only(self::AUDITADOS));
            ['accion' => $accion, 'movimiento' => $movimiento] = $cambio($e);
            $e->forceFill(['ultimo_movimiento_at' => now()])->save();

            $mov = Movimiento::create([...$movimiento, 'expediente_id' => $e->id, 'user_id' => Auth::id()]);
            $despues = array_intersect_key($this->serializar($e->only(self::AUDITADOS)), array_flip(self::AUDITADOS));
            $cambiados = array_keys(array_filter($despues, fn ($v, $k) => $v !== $antes[$k], ARRAY_FILTER_USE_BOTH));
            $this->auditoria->registrar($accion, $e,
                antes: array_intersect_key($antes, array_flip($cambiados)),
                despues: [...array_intersect_key($despues, array_flip($cambiados)), 'movimiento_id' => $mov->id],
            );

            return $e;
        });

        return $expediente;
    }

    /**
     * @param  array<string, mixed>  $valores
     * @return array<string, mixed>
     */
    private function serializar(array $valores): array
    {
        return collect($valores)->map(fn ($v, $campo) => match (true) {
            $v instanceof BackedEnum => $v->value,
            // fecha_limite es un día; el resto, instantes.
            $v instanceof DateTimeInterface => $campo === 'fecha_limite' ? $v->format('Y-m-d') : CarbonImmutable::instance($v)->toIso8601String(),
            default => $v,
        })->all();
    }
}
