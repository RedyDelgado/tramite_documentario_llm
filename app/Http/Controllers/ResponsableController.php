<?php

namespace App\Http\Controllers;

use App\Http\Requests\ResponsableRequest;
use App\Http\Resources\ResponsableResource;
use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\User;
use App\Services\CatalogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ResponsableController extends Controller
{
    public function __construct(private readonly CatalogoService $catalogo) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AreaResponsable::class);

        $filtros = $request->validate([
            'area' => ['nullable', 'integer'],
            'estado' => ['nullable', 'in:vigentes,todos'],
        ]);

        $responsables = AreaResponsable::query()
            ->with(['area:id,nombre', 'user:id,name,email'])
            ->when($filtros['area'] ?? null, fn ($q, $id) => $q->where('area_id', $id))
            ->when(($filtros['estado'] ?? 'vigentes') === 'vigentes', fn ($q) => $q->vigentes())
            ->join('areas', 'areas.id', '=', 'area_responsables.area_id')
            ->orderBy('areas.nombre')
            ->orderBy('area_responsables.tipo', 'desc')
            ->orderByDesc('area_responsables.vigente_desde')
            ->select('area_responsables.*')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('responsables/Index', [
            'responsables' => ResponsableResource::collection($responsables),
            'filtros' => (object) $filtros,
            'opcionesArea' => Area::opciones(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', AreaResponsable::class);

        return $this->index($request)->with('formulario', ['responsable' => null, ...$this->opciones()]);
    }

    public function store(ResponsableRequest $request): RedirectResponse
    {
        $responsable = $this->catalogo->crear(new AreaResponsable($request->validated()), 'responsable.asignado');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "{$responsable->user->name} asignado como ".mb_strtolower(AreaResponsable::TIPOS[$responsable->tipo]).'.']);

        return to_route('responsables.index');
    }

    public function edit(Request $request, AreaResponsable $responsable): Response
    {
        Gate::authorize('update', $responsable);

        return $this->index($request)->with('formulario', [
            'responsable' => ResponsableResource::make($responsable->load(['area', 'user']))->resolve(),
            ...$this->opciones(),
        ]);
    }

    public function update(ResponsableRequest $request, AreaResponsable $responsable): RedirectResponse
    {
        $this->catalogo->actualizar($responsable, $request->validated(), 'responsable.actualizado');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => 'Responsable actualizado.']);

        return to_route('responsables.index');
    }

    /** @return array<string, list<array{value: int|string, label: string}>> */
    private function opciones(): array
    {
        return [
            'opcionesArea' => Area::opciones(),
            'opcionesUsuario' => User::opciones(),
            'opcionesTipo' => collect(AreaResponsable::TIPOS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all(),
        ];
    }
}
