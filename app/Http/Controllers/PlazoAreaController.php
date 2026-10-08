<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlazoAreaRequest;
use App\Http\Resources\PlazoAreaResource;
use App\Models\Area;
use App\Models\PlazoArea;
use App\Models\TipoTramite;
use App\Services\CatalogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PlazoAreaController extends Controller
{
    public function __construct(private readonly CatalogoService $catalogo) {}

    public function index(Request $request): Response
    {
        // Catálogo chico: va completo y se busca, filtra y pagina en el navegador (useListaLocal).
        Gate::authorize('viewAny', PlazoArea::class);

        $plazos = PlazoArea::query()
            ->with(['tipoTramite:id,nombre,plazo_dias,tipo_dias', 'area:id,nombre'])
            ->join('tipos_tramite', 'tipos_tramite.id', '=', 'plazos_area.tipo_tramite_id')
            ->orderBy('tipos_tramite.nombre')
            ->select('plazos_area.*')
            ->get();

        return Inertia::render('plazos/Index', [
            'plazos' => PlazoAreaResource::collection($plazos)->resolve($request),
            'opcionesTipo' => TipoTramite::opciones(),
            'opcionesArea' => Area::opciones(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', PlazoArea::class);

        return $this->index($request)->with('formulario', ['plazo' => null, ...$this->opciones()]);
    }

    public function store(PlazoAreaRequest $request): RedirectResponse
    {
        $this->catalogo->crear(new PlazoArea($request->validated()), 'plazo_area.creado');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => 'Plazo por área creado.']);

        return to_route('plazos.index');
    }

    public function edit(Request $request, PlazoArea $plazo): Response
    {
        Gate::authorize('update', $plazo);

        return $this->index($request)->with('formulario', [
            'plazo' => PlazoAreaResource::make($plazo->load(['tipoTramite', 'area']))->resolve(),
            ...$this->opciones(),
        ]);
    }

    public function update(PlazoAreaRequest $request, PlazoArea $plazo): RedirectResponse
    {
        $this->catalogo->actualizar($plazo, $request->validated(), 'plazo_area.actualizado');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => 'Plazo por área actualizado. Los expedientes ya ingresados conservan su plazo.']);

        return to_route('plazos.index');
    }

    public function destroy(PlazoArea $plazo): RedirectResponse
    {
        Gate::authorize('delete', $plazo);
        $this->catalogo->quitar($plazo, 'plazo_area.quitado');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => 'Plazo por área quitado: el área vuelve a usar el plazo del tipo.']);

        return to_route('plazos.index');
    }

    /** @return array<string, list<array{value: int, label: string}>> */
    private function opciones(): array
    {
        return ['opcionesTipo' => TipoTramite::opciones(), 'opcionesArea' => Area::opciones()];
    }
}
