<?php

namespace App\Http\Controllers;

use App\Http\Requests\TipoTramiteRequest;
use App\Http\Resources\TipoTramiteResource;
use App\Models\TipoTramite;
use App\Services\CatalogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TipoTramiteController extends Controller
{
    public function __construct(private readonly CatalogoService $catalogo) {}

    public function index(Request $request): Response
    {
        // Catálogo chico: va completo y se busca, filtra y pagina en el navegador (useListaLocal).
        Gate::authorize('viewAny', TipoTramite::class);

        $tipos = TipoTramite::query()
            ->orderBy('nombre')
            ->orderBy('id')
            ->get();

        return Inertia::render('tipos-tramite/Index', [
            'tipos' => TipoTramiteResource::collection($tipos)->resolve($request),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', TipoTramite::class);

        return $this->index($request)->with('formulario', ['tipo' => null, ...$this->opciones()]);
    }

    public function store(TipoTramiteRequest $request): RedirectResponse
    {
        $tipo = $this->catalogo->crear(new TipoTramite($request->validated()), 'tipo_tramite.creado');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Tipo de trámite «{$tipo->nombre}» creado."]);

        return to_route('tipos-tramite.index');
    }

    public function edit(Request $request, TipoTramite $tipo): Response
    {
        Gate::authorize('update', $tipo);

        return $this->index($request)->with('formulario', ['tipo' => TipoTramiteResource::make($tipo)->resolve(), ...$this->opciones()]);
    }

    public function update(TipoTramiteRequest $request, TipoTramite $tipo): RedirectResponse
    {
        $this->catalogo->actualizar($tipo, $request->validated(), 'tipo_tramite.actualizado');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Tipo de trámite «{$tipo->nombre}» actualizado. Los expedientes ya ingresados conservan su plazo."]);

        return to_route('tipos-tramite.index');
    }

    public function cambiarEstado(Request $request, TipoTramite $tipo): RedirectResponse
    {
        Gate::authorize('update', $tipo);

        $activo = (bool) $request->validate(['activo' => ['required', 'boolean']])['activo'];
        $this->catalogo->actualizar($tipo, ['activo' => $activo], $activo ? 'tipo_tramite.activado' : 'tipo_tramite.desactivado');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Tipo de trámite «{$tipo->nombre}» ".($activo ? 'activado.' : 'desactivado.')]);

        return back();
    }

    /** @return array<string, list<array{value: string, label: string}>> */
    private function opciones(): array
    {
        $opciones = fn (array $mapa) => collect($mapa)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();

        return ['opcionesDias' => $opciones(TipoTramite::TIPOS_DIAS), 'opcionesCierre' => $opciones(TipoTramite::APRUEBA_CIERRE)];
    }
}
