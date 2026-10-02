<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Configuracion;
use App\Models\CorreccionPendiente;
use App\Models\TipoTramite;
use App\Services\ClasificacionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/** Precisión de la IA y validación de correcciones (8, 10). */
class IaController extends Controller
{
    public function __construct(private readonly ClasificacionService $clasificacion) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', CorreccionPendiente::class);

        $pendientes = CorreccionPendiente::where('estado', 'pendiente')->with(['clasificacion.expediente', 'usuario:id,name'])->orderBy('id')->paginate(25);
        $areas = Area::pluck('nombre', 'id');
        $tipos = TipoTramite::pluck('nombre', 'id');
        $nombre = fn (string $campo, ?int $id) => $id === null ? null : ($campo === 'area' ? $areas[$id] ?? null : $tipos[$id] ?? null);

        return Inertia::render('ia/Index', [
            'precision' => $this->clasificacion->precision(),
            'correcciones' => JsonResource::collection($pendientes->through(fn (CorreccionPendiente $c) => [
                'id' => $c->id,
                'expediente_id' => $c->clasificacion->expediente_id,
                // Solo el número: quien administra la configuración no ve el contenido del trámite (5).
                'numero_registro' => $c->clasificacion->expediente->numero_registro,
                'campo' => $c->campo === 'area' ? 'Área' : 'Tipo de trámite',
                'valor_ia' => $nombre($c->campo, $c->valor_ia),
                'valor_humano' => $nombre($c->campo, $c->valor_humano),
                'usuario' => $c->usuario?->name,
                'puede_resolver' => $request->user()->can('resolver', $c),
            ])),
            'modo' => Configuracion::valor('ia.modo'),
            'modelo' => $this->modeloActivo(),
        ]);
    }

    public function resolver(Request $request, CorreccionPendiente $correccion): RedirectResponse
    {
        Gate::authorize('resolver', $correccion);
        $validar = (bool) $request->validate(['validar' => ['required', 'boolean']])['validar'];
        $this->clasificacion->resolverCorreccion($correccion, $validar, $request->user());

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => $validar ? 'Corrección validada: servirá para reentrenar.' : 'Corrección rechazada.']);

        return back();
    }

    /** @return array{version: ?string, entrenado_en: ?string, ejemplos: ?int}|null null si el servicio de IA no responde */
    private function modeloActivo(): ?array
    {
        try {
            return Http::baseUrl(config('tramite.ai.url'))->withHeaders(['X-AI-Token' => (string) config('tramite.ai.token')])
                ->timeout(5)->get('/model-info')->throw()->json();
        } catch (Throwable) {
            return null;
        }
    }
}
