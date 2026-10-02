<?php

namespace App\Http\Controllers;

use App\Enums\EstadoExpediente;
use App\Http\Resources\ExpedienteResource;
use App\Models\Auditoria;
use App\Models\Correo;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\ExpedienteService;
use App\Support\AccionesAuditoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ExpedienteController extends Controller
{
    public function __construct(private readonly ExpedienteService $expedientes) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Expediente::class);

        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'estado' => ['nullable', Rule::enum(EstadoExpediente::class)],
            'dir' => ['nullable', 'in:asc,desc'],
        ]);
        $user = $request->user();

        if ($texto = $filtros['q'] ?? null) {
            // Meilisearch filtra por permisos (visible_para); el scope en la base es la segunda barrera.
            $busqueda = Expediente::search($texto)
                ->query(fn ($q) => $q->visiblesPara($user)->with('area:id,nombre')->withCount('documentos'));
            if (($tokens = Expediente::tokensVisiblesPara($user)) !== null) {
                $busqueda->whereIn('visible_para', $tokens);
            }
            if ($estado = $filtros['estado'] ?? null) {
                $busqueda->where('estado', $estado);
            }
            $pagina = $busqueda->paginate(25);
        } else {
            $pagina = Expediente::visiblesPara($user)
                ->with('area:id,nombre')
                ->withCount('documentos')
                ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
                ->orderBy('fecha_ingreso', $filtros['dir'] ?? 'desc')
                ->orderByDesc('id')
                ->paginate(25);
        }

        return Inertia::render('expedientes/Index', [
            'expedientes' => ExpedienteResource::collection($pagina->withQueryString()),
            'filtros' => (object) $filtros,
            'estados' => collect(EstadoExpediente::cases())->map(fn ($e) => ['value' => $e->value, 'label' => $e->etiqueta()]),
        ]);
    }

    public function show(Request $request, Expediente $expediente, AuditoriaService $auditoria): Response
    {
        Gate::authorize('view', $expediente);

        // Leer el contenido de un trámite queda registrado (9).
        $auditoria->registrar('expediente.consultado', $expediente);
        $expediente->load(['correos.documentos', 'documentos', 'area:id,nombre', 'responsable:id,name']);

        return Inertia::render('expedientes/Show', [
            'expediente' => [
                ...ExpedienteResource::make($expediente)->resolve($request),
                'origen' => $expediente->origen->value,
                'registrado_at' => $expediente->registrado_at?->toIso8601String(),
                'motivo_anulacion' => $expediente->motivo_anulacion,
                'responsable' => $expediente->responsable?->name,
                'correos' => $expediente->correos->map(fn (Correo $c) => [
                    'id' => $c->id,
                    'de_nombre' => $c->de_nombre,
                    'de_email' => $c->de_email,
                    'para' => collect($c->para)->pluck('email')->all(),
                    'asunto' => $c->asunto,
                    'fecha' => $c->fecha->toIso8601String(),
                    'cuerpo' => $c->cuerpo_texto,
                    'es_reenvio' => $c->es_reenvio,
                    'documentos' => $c->documentos->pluck('id'),
                ]),
                'documentos' => $expediente->documentos->map(fn (Documento $d) => [
                    'id' => $d->id,
                    'nombre' => $d->nombre_original,
                    'mime' => $d->mime,
                    'tamano' => $d->tamano,
                    'sha256' => $d->sha256,
                    'con_texto' => $d->texto_extraido !== null,
                ]),
            ],
            'historial' => $this->historial($expediente),
        ]);
    }

    public function confirmar(Expediente $expediente): RedirectResponse
    {
        Gate::authorize('registrar', $expediente);
        $expediente = $this->expedientes->confirmar($expediente);

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

        return $eventos->map(fn (Auditoria $a) => [
            'id' => $a->id,
            'fecha' => $a->fecha_hora->toIso8601String(),
            'accion' => AccionesAuditoria::etiqueta($a->accion),
            'usuario' => $a->usuario_id ? ($usuarios[$a->usuario_id] ?? "Usuario {$a->usuario_id}") : 'Sistema',
        ])->all();
    }
}
