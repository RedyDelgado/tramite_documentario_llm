<?php

namespace App\Services;

use App\Enums\EstadoExpediente;
use App\Exceptions\ReglaDeNegocio;
use App\Mail\AvisoDerivacion;
use App\Models\AreaResponsable;
use App\Models\Correo;
use App\Models\DocumentoSaliente;
use App\Models\Expediente;
use App\Models\ExpedienteArea;
use App\Models\Movimiento;
use App\Models\NotificacionEnviada;
use App\Models\TipoTramite;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Closure;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;

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
     * Deriva o reasigna a una o varias áreas que lo atienden, cada una con su responsable y su plazo (decisión del usuario).
     * El plazo sale del tipo y del área (5.1); una fecha explícita del documento manda (8). La primera área es la principal.
     * Las áreas en copia lo ven sin atenderlo; si vienen, reemplazan a las anteriores.
     *
     * @param  array{tipo_tramite_id: int, areas: list<array{area_id: int, responsable_id?: ?int, fecha_limite?: ?string}>, requiere_respuesta: bool, instruccion?: ?string, nota?: ?string, areas_copia?: list<int>}  $datos
     */
    public function derivar(Expediente $expediente, array $datos): Expediente
    {
        $avisar = [];
        $copiasNuevas = [];
        $expediente = $this->paso($expediente, [EstadoExpediente::Registrado, ...self::ABIERTOS], 'derivar', function (Expediente $e) use ($datos, &$avisar, &$copiasNuevas) {
            $tipo = TipoTramite::findOrFail($datos['tipo_tramite_id']);
            $pedidas = collect($datos['areas'])->keyBy(fn (array $a) => (int) $a['area_id']);
            $actuales = $e->atenciones()->with('area:id,nombre')->get()->keyBy('area_id');

            foreach ($actuales->diffKeys($pedidas) as $quitada) {
                if (! $quitada->pendiente()) {
                    throw new ReglaDeNegocio("«{$quitada->area->nombre}» ya lo atendió: no se puede quitar.");
                }
                $quitada->delete();
            }
            foreach ($pedidas as $areaId => $pedida) {
                $fila = $actuales->get($areaId) ?? new ExpedienteArea(['expediente_id' => $e->id, 'area_id' => $areaId, 'estado' => 'derivado']);
                // Lo que un área ya atendió no cambia al reasignar las demás.
                if (! $fila->pendiente()) {
                    continue;
                }
                $responsable = isset($pedida['responsable_id']) ? (int) $pedida['responsable_id'] : null;
                if (! $fila->exists || $fila->responsable_id !== $responsable) {
                    // Destino nuevo: empieza por tomarlo y recibe su aviso.
                    $fila->estado = 'derivado';
                    $avisar[] = $areaId;
                }
                $fila->responsable_id = $responsable;
                if ($pedida['fecha_limite'] ?? null) {
                    $fila->fecha_limite = $pedida['fecha_limite'];
                } elseif (! $fila->exists || $e->tipo_tramite_id !== $tipo->id || $fila->plazo_dias_aplicado === null) {
                    // Tipo o área nuevos: rige su plazo vigente; reasignar sin cambios conserva la fecha ya fijada.
                    $plazo = $this->plazos->calcular($tipo, $areaId, $e->fecha_ingreso);
                    $fila->forceFill(['plazo_dias_aplicado' => $plazo['plazo_dias'], 'fecha_limite' => $plazo['fecha_limite']?->toDateString()]);
                }
                $fila->save();
            }

            $principal = $pedidas->first();
            $deArea = $e->area_principal_id;
            $e->forceFill([
                'tipo_tramite_id' => $tipo->id,
                'area_principal_id' => (int) $principal['area_id'],
                'responsable_id' => $principal['responsable_id'] ?? null,
                'requiere_respuesta' => $datos['requiere_respuesta'],
                'cierre_solicitado_at' => null,
            ]);
            $this->resumir($e);

            if (array_key_exists('areas_copia', $datos)) {
                $antes = $e->areasCopia()->pluck('areas.id')->all();
                // Un área que lo atiende no va además en copia.
                $e->areasCopia()->sync(array_values(array_diff(array_map('intval', $datos['areas_copia']), $pedidas->keys()->all())));
                $e->unsetRelation('areasCopia');
                $copiasNuevas = array_values(array_diff($e->areasCopia()->pluck('areas.id')->all(), $antes));
            }
            $copias = $e->areasCopia()->orderBy('nombre')->pluck('nombre')->all();
            $filas = $e->atenciones()->with(['area:id,nombre', 'responsable:id,name'])->get();

            return [
                'accion' => 'expediente.derivado',
                'movimiento' => [
                    'tipo' => 'derivacion',
                    'de_area_id' => $deArea,
                    'a_area_id' => $e->area_principal_id,
                    'a_user_id' => $e->responsable_id,
                    'instruccion' => $datos['instruccion'] ?? null,
                    'nota' => $datos['nota'] ?? null,
                    'fecha_limite' => $e->fecha_limite?->toDateString(),
                    // Nombres tal como estaban al derivar: el historial no cambia si luego se renombra el área.
                    'areas' => $filas->map(fn (ExpedienteArea $a) => $a->area->nombre.($a->responsable ? " ({$a->responsable->name})" : ''))->all(),
                    'areas_copia' => $copias ?: null,
                ],
            ];
        });
        // La derivación es la decisión humana con la que se mide la IA (10).
        $this->clasificacion->registrarDecision($expediente, Auth::user());
        // Reasignar al mismo destino (p. ej. solo cambiar el plazo) no vuelve a avisar.
        $this->avisarDerivacion($expediente, $avisar, $copiasNuevas);

        return $expediente;
    }

    /**
     * Aviso por correo al derivar (8): a cada área nueva, su responsable o quienes la coordinan hoy; a cada área nueva
     * en copia, quienes la coordinan, para conocimiento. Queda constancia de cada correo (NotificacionEnviada y auditoría).
     *
     * @param  list<int>  $areas
     * @param  list<int>  $copias
     */
    private function avisarDerivacion(Expediente $expediente, array $areas, array $copias): void
    {
        if ($areas === [] && $copias === []) {
            return;
        }
        $movimiento = Movimiento::where('expediente_id', $expediente->id)->where('tipo', 'derivacion')->latest('id')->firstOrFail();
        $coordinan = fn (int $area) => AreaResponsable::where('area_id', $area)->vigentes()->pluck('user_id')->all();

        $avisos = [];
        foreach ($expediente->atenciones()->whereIn('area_id', $areas)->with('area:id,nombre')->get() as $atencion) {
            foreach ($atencion->responsable_id ? [$atencion->responsable_id] : $coordinan($atencion->area_id) as $id) {
                $avisos[$id] ??= $atencion;
            }
        }
        $enCopia = [];
        foreach ($copias as $area) {
            foreach ($coordinan($area) as $id) {
                $enCopia[$id] = true;
            }
        }

        // Quien deriva a sí mismo no necesita aviso; quien atiende no recibe además el de copia.
        User::whereIn('id', [...array_keys($avisos), ...array_keys($enCopia)])->whereKeyNot(Auth::id())->where('activo', true)
            ->each(function (User $destinatario) use ($expediente, $movimiento, $avisos) {
                $atencion = $avisos[$destinatario->id] ?? null;
                $tipo = $atencion ? 'derivacion' : 'copia';
                $notificacion = NotificacionEnviada::create([
                    'user_id' => $destinatario->id,
                    'email' => $destinatario->email,
                    'tipo' => $tipo,
                    'expedientes' => [$expediente->id],
                    'message_id' => NotificacionEnviada::nuevoMessageId($tipo),
                ]);
                Mail::mailer(app(BuzonService::class)->mailer())->to($destinatario)->queue(new AvisoDerivacion($destinatario, $expediente, $movimiento, $notificacion, $atencion));
                $this->auditoria->registrar("notificacion.{$tipo}", $destinatario, despues: [
                    'destinatario' => $destinatario->email,
                    'expediente_id' => $expediente->id,
                    'area_id' => $atencion?->area_id,
                    'notificacion_id' => $notificacion->id,
                ]);
            });
    }

    /** Quien atiende toma lo de su área; si es solo para conocimiento y sin plazo, tomarlo ya lo deja atendido (8). */
    public function tomar(Expediente $expediente): Expediente
    {
        return $this->paso($expediente, self::ABIERTOS, 'tomar', function (Expediente $e) {
            $filas = Gate::getPolicyFor($e)->areasQueAtiende(Auth::user(), $e)->where('estado', 'derivado')->values();
            if ($filas->isEmpty()) {
                throw new ReglaDeNegocio('No tienes nada por tomar en este expediente.');
            }
            $soloConocimiento = ! $e->requiere_respuesta && $filas->every(fn (ExpedienteArea $a) => $a->fecha_limite === null);
            $filas->each(fn (ExpedienteArea $a) => $a->forceFill($soloConocimiento
                ? ['estado' => 'atendido', 'atendido_at' => now(), 'atendido_por' => Auth::id()]
                : ['estado' => 'en_atencion'])->save());
            $this->resumir($e);

            return ['accion' => 'expediente.'.($soloConocimiento ? 'conocimiento_tomado' : 'en_atencion'), 'movimiento' => [
                'tipo' => $soloConocimiento ? 'toma_conocimiento' : 'en_atencion', ...$this->destino($filas),
            ]];
        });
    }

    /** El área deja constancia de cómo lo atendió; el expediente queda atendido cuando lo atendieron todas. */
    public function atender(Expediente $expediente, string $nota, ?array $soloAreas = null): Expediente
    {
        return $this->paso($expediente, self::ABIERTOS, 'marcar como atendido', function (Expediente $e) use ($nota, $soloAreas) {
            $filas = $soloAreas === null
                ? Gate::getPolicyFor($e)->areasQueAtiende(Auth::user(), $e)
                : $e->atenciones()->where('estado', '!=', 'atendido')->whereIn('area_id', $soloAreas)->get();
            if ($filas->isEmpty()) {
                throw new ReglaDeNegocio('No tienes nada pendiente en este expediente.');
            }
            $filas->each(fn (ExpedienteArea $a) => $a->forceFill(['estado' => 'atendido', 'atendido_at' => now(), 'atendido_por' => Auth::id(), 'nota_atencion' => $nota])->save());
            $this->resumir($e);

            return ['accion' => $e->estado === EstadoExpediente::Atendido ? 'expediente.atendido' : 'expediente.area_atendida', 'movimiento' => [
                'tipo' => 'atendido', 'nota' => $nota, ...$this->destino($filas),
            ]];
        });
    }

    /**
     * La respuesta por correo atiende solo el área de quien la envió (su responsable o quien la coordina hoy), no las
     * demás derivadas; quien coordina no entra al sistema, así que la confirma quien deriva o registra.
     */
    public function atenderConCorreo(Correo $correo): Expediente
    {
        $e = $correo->expediente;
        $email = mb_strtolower($correo->de_email);
        $pendientes = $e->atenciones()->where('estado', '!=', 'atendido')->with('responsable:id,email')->get();
        $coordina = AreaResponsable::vigentes()->whereIn('area_id', $pendientes->pluck('area_id'))
            ->whereHas('user', fn ($q) => $q->whereRaw('lower(email) = ?', [$email]))->pluck('area_id');
        $areas = $pendientes->filter(fn (ExpedienteArea $a) => mb_strtolower((string) $a->responsable?->email) === $email || $coordina->contains($a->area_id))
            ->pluck('area_id')->all();
        if ($areas === [] && ! Gate::allows('atender', $e)) {
            throw new ReglaDeNegocio("{$correo->de_email} no es responsable ni coordina un área pendiente de este expediente.");
        }
        $de = $correo->de_nombre ?? $correo->de_email;

        // Remitente no reconocido: lo que atiende quien confirma, como al marcarlo a mano.
        return $this->atender($e, "Respondió por correo {$de} el {$correo->fecha->timezone('America/Lima')->format('d/m/Y H:i')}: «{$correo->asunto}»", $areas ?: null);
    }

    /** Enviada la respuesta vinculada, el expediente queda atendido sin que nadie lo marque (7.3.5, punto 7). */
    public function atenderConRespuesta(Expediente $expediente, DocumentoSaliente $respuesta): Expediente
    {
        return $this->paso($expediente, [EstadoExpediente::Registrado, ...self::ABIERTOS], 'atender', function (Expediente $e) use ($respuesta) {
            $e->atenciones()->where('estado', '!=', 'atendido')
                ->update(['estado' => 'atendido', 'atendido_at' => now(), 'atendido_por' => Auth::id(), 'nota_atencion' => "Respuesta enviada: {$respuesta->numero}"]);
            $e->forceFill(['estado' => EstadoExpediente::Atendido, 'atendido_at' => now(), 'cierre_solicitado_at' => null]);

            return ['accion' => 'expediente.respondido', 'movimiento' => ['tipo' => 'respuesta', 'nota' => "Respuesta enviada: {$respuesta->numero}"]];
        });
    }

    /**
     * Resume en el expediente lo de sus áreas: atendido cuando lo atendieron todas, en atención si alguna lo tomó.
     * ponytail: la fecha límite del expediente es la más próxima de lo pendiente y, ya atendido, la más lejana; el panel
     * mide «en plazo» el expediente entero, no cada área.
     */
    private function resumir(Expediente $e): void
    {
        $filas = $e->atenciones()->get();
        $pendientes = $filas->filter(fn (ExpedienteArea $a) => $a->pendiente());
        $estado = match (true) {
            $filas->isNotEmpty() && $pendientes->isEmpty() => EstadoExpediente::Atendido,
            $filas->contains(fn (ExpedienteArea $a) => $a->estado !== 'derivado') => EstadoExpediente::EnAtencion,
            default => EstadoExpediente::Derivado,
        };
        $e->forceFill([
            'estado' => $estado,
            'plazo_dias_aplicado' => $filas->firstWhere('area_id', $e->area_principal_id)?->plazo_dias_aplicado,
            'fecha_limite' => ($pendientes->isNotEmpty() ? $pendientes->min('fecha_limite') : $filas->max('fecha_limite'))?->toDateString(),
            'atendido_at' => $estado === EstadoExpediente::Atendido ? ($e->atendido_at ?? now()) : null,
        ]);
        $e->unsetRelation('atenciones');
    }

    /**
     * El área del movimiento y, si quien actúa atiende varias, todas.
     *
     * @param  Collection<int, ExpedienteArea>  $filas
     * @return array{a_area_id: int, areas: ?list<string>}
     */
    private function destino(Collection $filas): array
    {
        $filas->load('area:id,nombre');

        return ['a_area_id' => $filas->first()->area_id, 'areas' => $filas->count() > 1 ? $filas->map(fn (ExpedienteArea $a) => $a->area->nombre)->all() : null];
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
            // Si quien lo pide ya tiene la facultad de aprobarlo, la aprobación sería la suya: se cierra.
            if ($e->tipoTramite?->aprueba_cierre && ! Gate::allows('cerrarSinAprobacion', $e)) {
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
