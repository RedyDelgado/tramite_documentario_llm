<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalienteRequest;
use App\Models\Area;
use App\Models\DocumentoSaliente;
use App\Models\Expediente;
use App\Models\TipoDocumento;
use App\Policies\SalientePolicy;
use App\Services\AuditoriaService;
use App\Services\EnvioService;
use App\Services\SalienteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** Documentos que emite la institución (7.3.4). */
class SalienteController extends Controller
{
    public function __construct(private readonly SalienteService $salientes) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', DocumentoSaliente::class);
        $user = $request->user();
        $filtros = $request->validate(['estado' => ['nullable', 'in:'.implode(',', array_keys(DocumentoSaliente::ESTADOS))]]);

        $salientes = DocumentoSaliente::query()
            ->with(['tipoDocumento:id,nombre', 'area:id,nombre', 'expediente'])
            ->withCount(['envios as rebotes' => fn ($q) => $q->where('estado', 'rebotado')])
            // Mismo criterio que SalientePolicy::view, en la consulta.
            ->unless($user->can('expedientes.ver_todos'), fn ($q) => $q->where(fn ($q) => $q
                ->where('creado_por', $user->id)
                ->orWhereIn('area_id', $user->areasVigentes())
                ->orWhereIn('expediente_id', Expediente::visiblesPara($user)->select('id'))))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('salientes/Index', [
            'salientes' => JsonResource::collection($salientes->through(fn (DocumentoSaliente $s) => $this->fila($s))),
            'filtros' => (object) $filtros,
            'estados' => collect(DocumentoSaliente::ESTADOS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', DocumentoSaliente::class);
        $expediente = $request->integer('expediente') ? Expediente::findOrFail($request->integer('expediente')) : null;
        if ($expediente) {
            Gate::authorize('view', $expediente);
        }

        return $this->index($request)->with('formulario', [
            'saliente' => null,
            'expediente' => $expediente ? [
                'id' => $expediente->id, 'codigo' => $expediente->codigo, 'asunto' => $expediente->asunto,
                'remitente' => ['email' => $expediente->remitente_email, 'nombre' => $expediente->remitente_nombre],
                'area_id' => $expediente->area_principal_id,
            ] : null,
            ...$this->opciones($request),
        ]);
    }

    public function store(SalienteRequest $request): RedirectResponse
    {
        $saliente = $this->salientes->crear($request->safe()->except('archivo'), $request->file('archivo'), $request->user());

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => 'Borrador guardado.']);

        return to_route('salientes.show', $saliente);
    }

    public function show(Request $request, DocumentoSaliente $saliente): Response
    {
        Gate::authorize('view', $saliente);
        $saliente->load(['tipoDocumento', 'area', 'expediente', 'autor:id,name', 'aprobador:id,name', 'envios']);
        $user = $request->user();

        return $this->index($request)->with('detalle', [
            ...$this->fila($saliente),
            'mensaje' => $saliente->cuerpo,
            'borrador' => $saliente->nombre_borrador,
            'final' => $saliente->nombre_firmado,
            'sha256_final' => $saliente->sha256_firmado,
            'destinatarios' => $saliente->destinatarios,
            'autor' => $saliente->autor->name,
            'aprobador' => $saliente->aprobador?->name,
            'aprobado_at' => $saliente->aprobado_at?->toIso8601String(),
            'observacion' => $saliente->observacion,
            'es_respuesta' => $saliente->es_respuesta,
            'requiere_respuesta' => $saliente->requiere_respuesta,
            'plazo_respuesta_dias' => $saliente->plazo_respuesta_dias,
            'envios' => $saliente->envios->map(fn ($e) => [
                'id' => $e->id, 'email' => $e->email, 'nombre' => $e->nombre, 'estado' => $e->estado,
                'enviado_at' => $e->enviado_at?->toIso8601String(), 'detalle' => $e->detalle,
            ]),
            'permisos' => [
                'editar' => $user->can('update', $saliente),
                'revision' => $saliente->estado === 'borrador' && $user->can('update', $saliente),
                'aprobar' => $user->can('aprobar', $saliente),
                'firmar' => $user->can('firmar', $saliente),
            ],
        ]);
    }

    public function edit(Request $request, DocumentoSaliente $saliente): Response
    {
        Gate::authorize('update', $saliente);

        return $this->index($request)->with('formulario', [
            'saliente' => [
                'id' => $saliente->id, 'expediente_id' => $saliente->expediente_id,
                'tipo_documento_id' => $saliente->tipo_documento_id, 'area_id' => $saliente->area_id, 'asunto' => $saliente->asunto,
                'cuerpo' => $saliente->cuerpo, 'destinatarios' => $saliente->destinatarios, 'es_respuesta' => $saliente->es_respuesta,
                'requiere_respuesta' => $saliente->requiere_respuesta, 'plazo_respuesta_dias' => $saliente->plazo_respuesta_dias,
                'borrador' => $saliente->nombre_borrador, 'observacion' => $saliente->observacion,
            ],
            'expediente' => null,
            ...$this->opciones($request),
        ]);
    }

    public function update(SalienteRequest $request, DocumentoSaliente $saliente): RedirectResponse
    {
        $this->salientes->actualizar($saliente, $request->safe()->except('archivo'), $request->file('archivo'));

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => 'Borrador actualizado.']);

        return to_route('salientes.show', $saliente);
    }

    public function revision(Request $request, DocumentoSaliente $saliente): RedirectResponse
    {
        Gate::authorize('update', $saliente);
        $this->salientes->enviarARevision($saliente);

        return $this->listo('Enviado a revisión.');
    }

    public function devolver(Request $request, DocumentoSaliente $saliente): RedirectResponse
    {
        Gate::authorize('aprobar', $saliente);
        $observacion = $request->validate(['observacion' => ['required', 'string', 'max:2000']], attributes: ['observacion' => 'observación'])['observacion'];
        $this->salientes->devolver($saliente, $observacion, $request->user());

        return $this->listo('Devuelto al autor con tu observación.');
    }

    public function aprobar(Request $request, DocumentoSaliente $saliente): RedirectResponse
    {
        Gate::authorize('aprobar', $saliente);
        $saliente = $this->salientes->aprobar($saliente, $request->user());

        return $this->listo("Aprobado como {$saliente->numero}.");
    }

    public function firmado(Request $request, DocumentoSaliente $saliente, EnvioService $envios): RedirectResponse
    {
        Gate::authorize('firmar', $saliente);
        $archivo = $request->validate(['archivo' => SalienteRequest::reglasArchivo(true)], attributes: ['archivo' => 'documento final'])['archivo'];
        $envios->subirFirmado($saliente, $archivo->getContent(), $archivo->getClientOriginalName());

        return $this->listo('Documento final subido: se está enviando a sus destinatarios.');
    }

    /** El borrador que se revisó o el documento final que salió, tal como se subieron; cada descarga queda auditada (9). */
    public function descargar(Request $request, DocumentoSaliente $saliente, string $formato, AuditoriaService $auditoria): HttpResponse
    {
        Gate::authorize('view', $saliente);
        [$ruta, $original] = match ($formato) {
            'borrador' => [$saliente->ruta_borrador, $saliente->nombre_borrador],
            'final' => [$saliente->ruta_firmado, $saliente->nombre_firmado],
            default => abort(404),
        };
        abort_unless($ruta !== null, 404);
        $extension = strtolower(pathinfo((string) $original, PATHINFO_EXTENSION)) ?: 'pdf';
        $nombre = str($saliente->numero ?? "borrador-{$saliente->id}")->slug();
        $auditoria->registrar('saliente.descargado', $saliente, despues: ['formato' => $formato]);

        return response(Storage::disk('originales')->get($ruta), 200, [
            'Content-Type' => DocumentoSaliente::mime($original),
            'Content-Disposition' => "attachment; filename=\"{$nombre}.{$extension}\"",
        ]);
    }

    private function listo(string $mensaje): RedirectResponse
    {
        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => $mensaje]);

        return back();
    }

    /** Forma espejada en resources/js/types/index.ts (Saliente). */
    private function fila(DocumentoSaliente $s): array
    {
        return [
            'id' => $s->id,
            'numero' => $s->numero,
            'asunto' => $s->asunto,
            'tipo' => $s->tipoDocumento->nombre,
            'area' => $s->area->nombre,
            'estado' => ['valor' => $s->estado, 'etiqueta' => DocumentoSaliente::ESTADOS[$s->estado]],
            'expediente' => $s->expediente ? ['id' => $s->expediente->id, 'numero_registro' => $s->expediente->numero_registro] : null,
            'semaforo' => $s->semaforo()?->value,
            'fecha_limite_respuesta' => $s->fecha_limite_respuesta?->toDateString(),
            'respondido_at' => $s->respondido_at?->toIso8601String(),
            'enviado_at' => $s->enviado_at?->toIso8601String(),
            'rebotes' => $s->rebotes ?? $s->envios()->where('estado', 'rebotado')->count(),
            'actualizado' => $s->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function opciones(Request $request): array
    {
        $areas = SalientePolicy::areasEmisoras($request->user());

        return [
            'opcionesTipo' => TipoDocumento::opciones(),
            'opcionesArea' => $areas === null ? Area::opciones() : collect(Area::opciones())->whereIn('value', $areas)->values()->all(),
        ];
    }
}
