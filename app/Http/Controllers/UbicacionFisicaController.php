<?php

namespace App\Http\Controllers;

use App\Http\Requests\UbicacionFisicaRequest;
use App\Models\UbicacionFisica;
use App\Services\CatalogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Dónde se guardan los originales en papel (7.3.1). */
class UbicacionFisicaController extends Controller
{
    public function __construct(private readonly CatalogoService $catalogo) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', UbicacionFisica::class);

        $filtros = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'estado' => ['nullable', 'in:activas,inactivas']]);

        $ubicaciones = UbicacionFisica::query()
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->whereLike('nombre', "%{$texto}%"))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('activa', $estado === 'activas'))
            ->orderBy('nombre')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('ubicaciones/Index', [
            'ubicaciones' => $ubicaciones->through(fn (UbicacionFisica $i) => $this->fila($i)),
            'filtros' => (object) $filtros,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', UbicacionFisica::class);

        return Inertia::render('ubicaciones/Form', ['ubicacion' => null]);
    }

    public function store(UbicacionFisicaRequest $request): RedirectResponse
    {
        $this->catalogo->crear(new UbicacionFisica($request->validated()), 'ubicacion.creada');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => 'Ubicación creada.']);

        return to_route('ubicaciones.index');
    }

    public function edit(UbicacionFisica $ubicacion): Response
    {
        Gate::authorize('update', $ubicacion);

        return Inertia::render('ubicaciones/Form', ['ubicacion' => $this->fila($ubicacion)]);
    }

    public function update(UbicacionFisicaRequest $request, UbicacionFisica $ubicacion): RedirectResponse
    {
        $this->catalogo->actualizar($ubicacion, $request->validated(), 'ubicacion.actualizada');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => 'Ubicación actualizada.']);

        return to_route('ubicaciones.index');
    }

    /** Forma espejada en resources/js/types/index.ts (UbicacionFisica). */
    private function fila(UbicacionFisica $i): array
    {
        return ['id' => $i->id, 'nombre' => $i->nombre, 'descripcion' => $i->descripcion, 'activa' => $i->activa, 'actualizado' => $i->updated_at?->toIso8601String()];
    }
}
