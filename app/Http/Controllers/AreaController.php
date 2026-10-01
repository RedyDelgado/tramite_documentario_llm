<?php

namespace App\Http\Controllers;

use App\Http\Requests\AreaRequest;
use App\Http\Resources\AreaResource;
use App\Models\Area;
use App\Services\AreaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AreaController extends Controller
{
    /** Columnas ordenables expuestas a la tabla => columna real. */
    private const ORDEN = ['nombre' => 'nombre', 'orden' => 'orden', 'actualizada' => 'updated_at'];

    public function __construct(private readonly AreaService $areas) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Area::class);

        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'in:activas,inactivas'],
            'orden' => ['nullable', 'in:'.implode(',', array_keys(self::ORDEN))],
            'dir' => ['nullable', 'in:asc,desc'],
        ]);

        $areas = Area::query()
            ->with('padre:id,nombre')
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->whereLike('nombre', "%{$texto}%"))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('activa', $estado === 'activas'))
            ->orderBy(self::ORDEN[$filtros['orden'] ?? 'orden'], $filtros['dir'] ?? 'asc')
            ->orderBy('nombre')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('areas/Index', [
            'areas' => AreaResource::collection($areas),
            'filtros' => (object) $filtros,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Area::class);

        return Inertia::render('areas/Form', [
            'area' => null,
            'opcionesPadre' => $this->opcionesPadre(),
        ]);
    }

    public function store(AreaRequest $request): RedirectResponse
    {
        $area = $this->areas->crear($request->validated());

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Área «{$area->nombre}» creada."]);

        return to_route('areas.index');
    }

    public function edit(Area $area): Response
    {
        Gate::authorize('update', $area);

        return Inertia::render('areas/Form', [
            // resolve(): el recurso suelto va sin el envoltorio `data`.
            'area' => AreaResource::make($area)->resolve(),
            'opcionesPadre' => $this->opcionesPadre($area),
        ]);
    }

    public function update(AreaRequest $request, Area $area): RedirectResponse
    {
        $this->areas->actualizar($area, $request->validated());

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Área «{$area->nombre}» actualizada."]);

        return to_route('areas.index');
    }

    public function cambiarEstado(Request $request, Area $area): RedirectResponse
    {
        Gate::authorize('update', $area);

        $activa = $request->validate(['activa' => ['required', 'boolean']])['activa'];
        $this->areas->cambiarEstado($area, (bool) $activa);

        Inertia::flash('toast', [
            'tipo' => 'ok',
            'mensaje' => $activa ? "Área «{$area->nombre}» activada." : "Área «{$area->nombre}» desactivada.",
        ]);

        return back();
    }

    /** @return list<array{value: int, label: string}> */
    private function opcionesPadre(?Area $excluir = null): array
    {
        return Area::query()
            ->where('activa', true)
            ->when($excluir, fn ($q) => $q->whereKeyNot($excluir->id))
            ->orderBy('nombre')
            ->get(['id', 'nombre'])
            ->map(fn (Area $a) => ['value' => $a->id, 'label' => $a->nombre])
            ->all();
    }
}
