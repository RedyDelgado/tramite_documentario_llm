<?php

namespace App\Http\Controllers;

use App\Enums\EstadoExpediente;
use App\Enums\OrigenExpediente;
use App\Enums\Semaforo;
use App\Http\Resources\ExpedienteResource;
use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\Auditoria;
use App\Models\Correo;
use App\Models\Documento;
use App\Models\DocumentoSaliente;
use App\Models\Emisor;
use App\Models\Expediente;
use App\Models\ExpedienteArea;
use App\Models\Grupo;
use App\Models\InstruccionFrecuente;
use App\Models\Movimiento;
use App\Models\ReglaDerivacion;
use App\Models\TipoDocumento;
use App\Models\TipoTramite;
use App\Models\UbicacionFisica;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\BorradorIaService;
use App\Services\ClasificacionService;
use App\Services\ExpedienteService;
use App\Services\SerieService;
use App\Support\AccionesAuditoria;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExpedienteController extends Controller
{
    /** Pestañas de la bandeja: vista => estados que agrupa (null: todos). */
    private const VISTAS = [
        'por_revisar' => [EstadoExpediente::PorRevisar],
        'en_curso' => [EstadoExpediente::Registrado, EstadoExpediente::Derivado, EstadoExpediente::EnAtencion, EstadoExpediente::Atendido],
        'cerrados' => [EstadoExpediente::Cerrado, EstadoExpediente::Anulado],
        'no_tramite' => [EstadoExpediente::NoTramite],
        'historico' => [EstadoExpediente::Historico],
        'todos' => null,
    ];

    private const ETIQUETAS_VISTA = [
        'por_revisar' => 'Por revisar',
        'en_curso' => 'En trámite',
        'cerrados' => 'Cerrados',
        'no_tramite' => 'No trámite',
        'historico' => 'Histórico',
        'todos' => 'Todos',
    ];

    public function __construct(private readonly ExpedienteService $expedientes) {}

    public function index(Request $request): Response|RedirectResponse
    {
        Gate::authorize('viewAny', Expediente::class);

        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'estado' => ['nullable', Rule::enum(EstadoExpediente::class)],
            'semaforo' => ['nullable', Rule::enum(Semaforo::class)],
            'dir' => ['nullable', 'in:asc,desc'],
            'vista' => ['nullable', Rule::in(array_keys(self::VISTAS))],
        ]);
        $user = $request->user();
        // Quien registra empieza por los correos que esperan revisión; buscar o filtrar por estado mira en todo.
        $vista = $filtros['vista'] ?? match (true) {
            isset($filtros['q']) || isset($filtros['estado']) || isset($filtros['semaforo']) => 'todos',
            $user->can('expedientes.registrar') => 'por_revisar',
            default => 'en_curso',
        };
        $estadosVista = collect(self::VISTAS[$vista])->map(fn (EstadoExpediente $e) => $e->value)->all();

        // Un lector de QR o de código escribe «REG-2026-00038» (o su enlace): se abre directo el expediente.
        if (($texto = $filtros['q'] ?? null) && ($porCodigo = Expediente::porCodigoEn($texto)) && $user->can('view', $porCodigo)) {
            return to_route('expedientes.show', $porCodigo);
        }

        if ($texto) {
            // Meilisearch filtra por permisos (visible_para); el scope en la base es la segunda barrera.
            $busqueda = Expediente::search($texto)
                ->query(fn ($q) => $q->visiblesPara($user)->with(['area:id,nombre', 'institucion:id,nombre'])->withCount(['documentos', 'atenciones']));
            if (($tokens = Expediente::tokensVisiblesPara($user)) !== null) {
                $busqueda->whereIn('visible_para', $tokens);
            }
            if ($estado = $filtros['estado'] ?? null) {
                $busqueda->where('estado', $estado);
            }
            if ($estadosVista) {
                $busqueda->whereIn('estado', $estadosVista);
            }
            if ($semaforo = $filtros['semaforo'] ?? null) {
                $busqueda->where('semaforo', $semaforo);
            }
            $pagina = $busqueda->paginate(10);
        } else {
            $pagina = Expediente::visiblesPara($user)
                ->with(['area:id,nombre', 'institucion:id,nombre'])
                ->withCount(['documentos', 'atenciones'])
                ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
                ->when($estadosVista, fn ($q, $estados) => $q->whereIn('estado', $estados))
                ->when($filtros['semaforo'] ?? null, fn ($q, $semaforo) => $q->where('semaforo', $semaforo))
                ->orderBy('fecha_ingreso', $filtros['dir'] ?? 'desc')
                ->orderByDesc('id')
                ->paginate(10);
        }

        return Inertia::render('expedientes/Index', [
            'expedientes' => ExpedienteResource::collection($pagina->withQueryString()),
            'filtros' => (object) $filtros,
            'vista' => $vista,
            'vistas' => $this->vistas($user),
            'estados' => collect(EstadoExpediente::cases())->map(fn ($e) => ['value' => $e->value, 'label' => $e->etiqueta()]),
            'semaforos' => collect(Semaforo::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->etiqueta()]),
        ]);
    }

    /** @return list<array{valor: string, etiqueta: string, total: int}> pestañas con lo que el usuario ve en cada una */
    private function vistas(User $user): array
    {
        $porEstado = Expediente::visiblesPara($user)->toBase()->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');

        return collect(self::VISTAS)->map(fn (?array $estados, string $vista) => [
            'valor' => $vista,
            'etiqueta' => self::ETIQUETAS_VISTA[$vista],
            'total' => (int) ($estados === null ? $porEstado->sum() : collect($estados)->sum(fn (EstadoExpediente $e) => $porEstado[$e->value] ?? 0)),
        ])->values()->all();
    }

    /** Texto sugerido por la IA local para la respuesta (fase 6): solo se muestra; no se guarda ni se envía. */
    public function borradorIa(Request $request, Expediente $expediente, BorradorIaService $borradores): JsonResponse
    {
        Gate::authorize('responder', $expediente);

        return response()->json(['texto' => $borradores->sugerir($expediente, $request->user())]);
    }

    /** Indicaciones de la derivación con la IA local: las redacta desde el documento o mejora las que escribió quien deriva. */
    public function indicacionesIa(Request $request, Expediente $expediente, BorradorIaService $ia): JsonResponse
    {
        Gate::authorize('derivar', $expediente);
        $datos = $request->validate([
            'texto' => ['nullable', 'string', 'max:2000'],
            'instruccion' => ['nullable', 'string', 'max:200'],
            'areas' => ['array', 'max:10'],
            'areas.*' => ['integer'],
        ]);
        $areas = Area::whereKey($datos['areas'] ?? [])->pluck('nombre')->all();

        return response()->json(['texto' => $ia->indicaciones($expediente, $datos['texto'] ?? null, $datos['instruccion'] ?? null, $areas, $request->user())]);
    }

    public function show(Request $request, Expediente $expediente, AuditoriaService $auditoria, SerieService $series): Response|RedirectResponse
    {
        Gate::authorize('view', $expediente);

        // Leer el contenido de un trámite queda registrado (9).
        $auditoria->registrar('expediente.consultado', $expediente);
        $user = $request->user();
        $registrable = $expediente->estado->puedeConfirmarse() && $user->can('registrar', $expediente);
        $expediente->load(['correos.documentos', 'documentos', 'area:id,nombre', 'responsable:id,name', 'emisor:id,nombre', 'institucion:id,nombre', 'tipoDocumento:id,nombre', 'tipoTramite', 'ubicacionFisica:id,nombre', 'custodio:id,name', 'movimientos.aArea:id,nombre', 'grupo', 'areasCopia:id,nombre', 'atenciones.area:id,nombre', 'atenciones.responsable:id,name,email', 'atenciones.atendidoPor:id,name']);
        $abierto = in_array($expediente->estado, [EstadoExpediente::Derivado, EstadoExpediente::EnAtencion], true);
        $derivable = ($abierto || $expediente->estado === EstadoExpediente::Registrado) && $user->can('derivar', $expediente);
        $custodia = $expediente->codigo !== null && $user->can('custodiar', $expediente);
        $agrupable = $user->can('agrupar', $expediente);
        // Lo que este usuario atiende: decide si ve «Tomar» y «Marcar atendido».
        $mias = $abierto ? Gate::getPolicyFor($expediente)->areasQueAtiende($user, $expediente) : collect();
        // De qué área viene cada correo: su remitente es el responsable o quien coordina un área que lo atiende.
        $nombres = $expediente->atenciones->pluck('area.nombre', 'area_id');
        $areaDe = AreaResponsable::vigentes()->whereIn('area_id', $nombres->keys())->with('user:id,email')->get()->toBase()
            ->map(fn (AreaResponsable $r) => [mb_strtolower($r->user->email), $r->area_id])
            ->merge($expediente->atenciones->filter(fn (ExpedienteArea $a) => $a->responsable)->map(fn (ExpedienteArea $a) => [mb_strtolower($a->responsable->email), $a->area_id]))
            ->groupBy(0)->map(fn ($filas) => $filas->map(fn ($f) => $nombres[$f[1]])->unique()->implode(', '));

        // El detalle se abre en modal sobre la bandeja (CLAUDE.md: ver va en un modal).
        $bandeja = $this->index($request);
        if ($bandeja instanceof RedirectResponse) {
            return $bandeja;
        }

        return $bandeja->with([
            'expediente' => [
                ...ExpedienteResource::make($expediente)->resolve($request),
                'origen' => $expediente->origen->value,
                'registrado_at' => $expediente->registrado_at?->toIso8601String(),
                'motivo_anulacion' => $expediente->motivo_anulacion,
                'emisor' => $expediente->emisor?->nombre,
                'institucion' => $expediente->institucion?->nombre,
                'tipo_documento' => $expediente->tipoDocumento?->nombre,
                'numero_documento' => $expediente->numero_documento_original ?? $expediente->numero_documento,
                'fecha_documento' => $expediente->fecha_documento?->toDateString(),
                'folios' => $expediente->folios,
                'motivo_folios' => $expediente->motivo_folios,
                'tipo_tramite_id' => $expediente->tipo_tramite_id,
                'tipo_tramite' => $expediente->tipoTramite?->nombre,
                'atenciones' => $expediente->atenciones->map(fn (ExpedienteArea $a) => [
                    'area_id' => $a->area_id,
                    'area' => $a->area->nombre,
                    'responsable_id' => $a->responsable_id,
                    'responsable' => $a->responsable?->name,
                    'plazo_dias' => $a->plazo_dias_aplicado,
                    'fecha_limite' => $a->fecha_limite?->toDateString(),
                    'estado' => ['valor' => $a->estado, 'etiqueta' => ExpedienteArea::ESTADOS[$a->estado]],
                    'atendido_at' => $a->atendido_at?->toIso8601String(),
                    'atendido_por' => $a->atendidoPor?->name,
                    'nota' => $a->nota_atencion,
                ])->values(),
                'areas_copia' => $expediente->areasCopia->map(fn (Area $a) => ['id' => $a->id, 'nombre' => $a->nombre])->values(),
                'plazo_dias_aplicado' => $expediente->plazo_dias_aplicado,
                'fecha_limite' => $expediente->fecha_limite?->toDateString(),
                'requiere_respuesta' => $expediente->requiere_respuesta,
                'cierre_solicitado_at' => $expediente->cierre_solicitado_at?->toIso8601String(),
                'atendido_at' => $expediente->atendido_at?->toIso8601String(),
                'permisos' => [
                    'derivar' => $derivable,
                    'tomar' => $mias->contains('estado', 'derivado'),
                    'atender' => $mias->isNotEmpty(),
                    // Dar por atendida un área con su respuesta por correo: quien coordina no entra al sistema.
                    'atender_correo' => $abierto && ($mias->isNotEmpty() || $user->can('responder', $expediente)),
                    'comentar' => ($abierto || $expediente->estado === EstadoExpediente::Registrado) && $user->can('comentar', $expediente),
                    'solicitar_cierre' => $abierto && ! $expediente->cierre_solicitado_at && $user->can('atender', $expediente),
                    'resolver_cierre' => $abierto && $expediente->cierre_solicitado_at && $user->can('aprobarCierre', $expediente),
                    'custodiar' => $custodia,
                    'agrupar' => $agrupable,
                    // Responder desde el sistema (7.2): solo lo registrado y aún sin cerrar.
                    'redactar' => ($responde = $expediente->codigo !== null && ! in_array($expediente->estado, [EstadoExpediente::Anulado, EstadoExpediente::Cerrado], true)
                        && $user->can('responder', $expediente)) && $user->can('create', DocumentoSaliente::class),
                    // El texto sugerido se pega en el Word: no depende de que los documentos emitidos estén activos.
                    'sugerir' => $responde && BorradorIaService::activo(),
                ],
                'serie' => $expediente->grupo ? [
                    'id' => $expediente->grupo->id,
                    'nombre' => $expediente->grupo->nombre,
                    'expedientes' => $expediente->grupo->expedientes()->visiblesPara($user)->orderBy('id')->get()
                        ->map(fn (Expediente $x) => $this->resumen($x)),
                ] : null,
                // El papel nunca se descarta: solo se registra dónde está y quién lo tiene (7.3.1).
                'original' => $expediente->origen === OrigenExpediente::Fisico ? [
                    'ubicacion_fisica_id' => $expediente->ubicacion_fisica_id,
                    'ubicacion' => $expediente->ubicacionFisica?->nombre,
                    'custodio_id' => $expediente->custodio_id,
                    'custodio' => $expediente->custodio?->name,
                ] : null,
                'cargos' => $expediente->movimientos->where('tipo', 'derivacion')->map(fn (Movimiento $m) => [
                    'id' => $m->id,
                    'fecha' => $m->created_at->toIso8601String(),
                    'area' => $m->areas ? implode(', ', $m->areas) : $m->aArea?->nombre,
                    'firmado' => $expediente->documentos->firstWhere('movimiento_id', $m->id)?->id,
                ])->values(),
                'correos' => $expediente->correos->map(fn (Correo $c) => [
                    'id' => $c->id,
                    'de_nombre' => $c->de_nombre,
                    'de_email' => $c->de_email,
                    'para' => collect($c->para)->pluck('email')->all(),
                    'asunto' => $c->asunto,
                    'fecha' => $c->fecha->toIso8601String(),
                    'cuerpo' => $c->cuerpo_texto,
                    'es_reenvio' => $c->es_reenvio,
                    'respuesta' => $c->respuesta_area ? ['area' => $areaDe[mb_strtolower($c->de_email)] ?? null, 'ia' => $c->ia_respuesta, 'motivo' => $c->ia_motivo] : null,
                    'documentos' => $c->documentos->pluck('id'),
                ]),
                'documentos' => $expediente->documentos->map(fn (Documento $d) => [
                    'id' => $d->id,
                    'nombre' => $d->nombre_original,
                    'mime' => $d->mime,
                    'tamano' => $d->tamano,
                    'sha256' => $d->sha256,
                    'con_texto' => $d->texto_extraido !== null,
                    'amenaza' => $d->amenaza,
                ]),
            ],
            'historial' => $this->historial($expediente),
            // Solo para el diálogo de registro (6.1).
            ...($registrable ? ['opcionesEmisor' => Emisor::opciones(), 'opcionesTipoDocumento' => TipoDocumento::opciones()] : []),
            ...($derivable ? ['derivacion' => $this->opcionesDerivacion($expediente)] : []),
            ...($agrupable && ! $expediente->grupo_id ? ['agrupacion' => [
                'parecidos' => $series->parecidos($expediente, $user)->map(fn (Expediente $x) => $this->resumen($x)),
                'series' => Grupo::latest('id')->limit(50)->get(['id', 'nombre'])->map(fn (Grupo $g) => ['value' => $g->id, 'label' => $g->nombre]),
            ]] : []),
            ...($custodia && $expediente->origen === OrigenExpediente::Fisico ? ['custodia' => ['ubicaciones' => UbicacionFisica::opciones(), 'usuarios' => User::opciones()]] : []),
        ]);
    }

    public function confirmar(Request $request, Expediente $expediente): RedirectResponse
    {
        Gate::authorize('registrar', $expediente);
        $datos = $request->validate([
            'emisor_id' => ['nullable', 'integer', Rule::exists('emisores', 'id')->where('activo', true)->whereNull('fusionado_en_id')],
            'institucion_id' => ['nullable', 'integer', Rule::exists('emisores', 'id')->where('activo', true)->where('clase', 'institucion')->whereNull('fusionado_en_id')],
            'tipo_documento_id' => ['nullable', 'integer', Rule::exists('tipos_documento', 'id')->where('activo', true)],
        ], attributes: ['emisor_id' => 'emisor', 'institucion_id' => 'institución', 'tipo_documento_id' => 'tipo de documento']);
        $expediente = $this->expedientes->confirmar($expediente, $datos);

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Registrado como {$expediente->numero_registro} ({$expediente->codigo})."]);

        return back();
    }

    public function noTramite(Expediente $expediente): RedirectResponse
    {
        Gate::authorize('registrar', $expediente);
        $this->expedientes->marcarNoTramite($expediente);

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => 'Archivado como no trámite. Puedes devolverlo a revisión cuando quieras.']);

        return back();
    }

    public function devolver(Expediente $expediente): RedirectResponse
    {
        Gate::authorize('registrar', $expediente);
        $this->expedientes->devolverARevision($expediente);

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => 'Devuelto a revisión.']);

        return back();
    }

    public function anular(Request $request, Expediente $expediente): RedirectResponse
    {
        Gate::authorize('registrar', $expediente);
        $motivo = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:500']])['motivo'];
        $expediente = $this->expedientes->anular($expediente, $motivo);

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Registro {$expediente->numero_registro} anulado; el número no se reutilizará."]);

        return back();
    }

    /** @return list<array{id: int, fecha: string, accion: string, usuario: string}> */
    /** @return array{id: int, numero_registro: ?string, asunto: string, estado: string} */
    private function resumen(Expediente $e): array
    {
        return ['id' => $e->id, 'numero_registro' => $e->numero_registro, 'asunto' => $e->asunto, 'estado' => $e->estado->etiqueta()];
    }

    /** Opciones del diálogo de derivación, con lo que sugiere la primera regla que aplica (5.1). */
    private function opcionesDerivacion(Expediente $expediente): array
    {
        $regla = ReglaDerivacion::primeraQueAplica($expediente);

        return [
            'tipos' => TipoTramite::where('activo', true)->orderBy('nombre')->get()
                ->map(fn (TipoTramite $t) => ['value' => $t->id, 'label' => $t->nombre.($t->plazo_dias ? " ({$t->plazo_dias} días ".($t->tipo_dias === 'habiles' ? 'hábiles' : 'calendario').')' : ' (sin plazo)')])
                ->all(),
            'areas' => Area::opciones(),
            'usuarios' => User::opciones(),
            'instrucciones' => InstruccionFrecuente::opciones(),
            // Quien recibe el aviso si no se elige responsable: el titular del área (y sus suplentes).
            'titulares' => AreaResponsable::vigentes()->where('tipo', 'titular')->with('user:id,name')->get()
                ->mapWithKeys(fn (AreaResponsable $r) => [$r->area_id => $r->user->name]),
            'redactar_con_ia' => BorradorIaService::activo(),
            'sugerencia' => $regla ? ['regla' => $regla->nombre, 'tipo_tramite_id' => $regla->tipo_tramite_id, 'area_id' => $regla->area_destino_id, 'responsable_id' => $regla->responsable_id] : null,
            'ia' => app(ClasificacionService::class)->sugerencia($expediente),
        ];
    }

    private function historial(Expediente $expediente): array
    {
        $eventos = Auditoria::query()
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('entidad', $expediente->getMorphClass())->where('entidad_id', (string) $expediente->id))
                ->orWhere(fn ($q) => $q->where('entidad', (new Correo)->getMorphClass())
                    ->whereIn('entidad_id', $expediente->correos->map(fn ($c) => (string) $c->id)))
                ->orWhere(fn ($q) => $q->where('entidad', (new Documento)->getMorphClass())
                    ->whereIn('entidad_id', $expediente->documentos->map(fn ($d) => (string) $d->id))))
            // Las consultas quedan auditadas, pero no se muestran: llenarían la línea de tiempo.
            ->where('accion', '!=', 'expediente.consultado')
            ->orderBy('id')
            ->get();
        $usuarios = User::whereIn('id', $eventos->pluck('usuario_id')->filter()->unique())->pluck('name', 'id');
        $movimientos = Movimiento::where('expediente_id', $expediente->id)->with(['aArea:id,nombre', 'aUser:id,name'])->get()->keyBy('id');
        $correos = $expediente->correos->keyBy(fn (Correo $c) => (string) $c->id);
        $esCorreo = (new Correo)->getMorphClass();

        return $eventos->map(fn (Auditoria $a) => [
            'id' => $a->id,
            'fecha' => $a->fecha_hora->toIso8601String(),
            'accion' => AccionesAuditoria::etiqueta($a->accion),
            'usuario' => $a->usuario_id ? ($usuarios[$a->usuario_id] ?? "Usuario {$a->usuario_id}") : 'Sistema',
            'detalle' => ($m = $movimientos->get($a->valor_nuevo['movimiento_id'] ?? 0)) ? $this->detalle($m)
                // Un correo que llega (una respuesta, un reenvío): de quién y sobre qué, sin abrirlo.
                : ($a->entidad === $esCorreo && ($c = $correos->get($a->entidad_id)) ? "De {$c->de_email}: «{$c->asunto}»" : null),
        ])->all();
    }

    /** Destino, instrucción, plazo y nota de un movimiento, en una línea legible. */
    private function detalle(Movimiento $m): ?string
    {
        $partes = array_filter([
            match (true) {
                (bool) $m->areas => 'A '.implode(', ', $m->areas),
                (bool) $m->aArea => 'A '.$m->aArea->nombre.($m->aUser ? " ({$m->aUser->name})" : ''),
                default => null,
            },
            $m->areas_copia ? 'Copia a '.implode(', ', $m->areas_copia) : null,
            $m->instruccion,
            $m->fecha_limite ? 'hasta el '.$m->fecha_limite->format('d/m/Y') : null,
            $m->nota,
        ]);

        return $partes === [] ? null : implode(' · ', $partes);
    }
}
