<?php

namespace App\Http\Controllers;

use App\Http\Requests\FeriadoRequest;
use App\Http\Resources\FeriadoResource;
use App\Models\Area;
use App\Models\Feriado;
use App\Services\CatalogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FeriadoController extends Controller
{
    public function __construct(private readonly CatalogoService $catalogo) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Feriado::class);

        $filtros = $request->validate([
            'anio' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'area' => ['nullable', 'integer'],
        ]);
        $anio = (int) ($filtros['anio'] ?? now()->year);

        $feriados = Feriado::query()
            ->with('area:id,nombre')
            ->whereYear('fecha', $anio)
            ->when($filtros['area'] ?? null, fn ($q, $id) => $q->where('area_id', $id))
            ->orderBy('fecha')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('feriados/Index', [
            'feriados' => FeriadoResource::collection($feriados),
            'filtros' => (object) [...$filtros, 'anio' => (string) $anio],
            'opcionesArea' => Area::opciones(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Feriado::class);

        return Inertia::render('feriados/Form', ['feriado' => null, 'opcionesArea' => Area::opciones()]);
    }

    public function store(FeriadoRequest $request): RedirectResponse
    {
        $feriado = $this->catalogo->crear(new Feriado($request->validated()), 'feriado.creado');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Feriado «{$feriado->descripcion}» registrado."]);

        return to_route('feriados.index', ['anio' => $feriado->fecha->year]);
    }

    public function edit(Feriado $feriado): Response
    {
        Gate::authorize('update', $feriado);

        return Inertia::render('feriados/Form', ['feriado' => FeriadoResource::make($feriado)->resolve(), 'opcionesArea' => Area::opciones()]);
    }

    public function update(FeriadoRequest $request, Feriado $feriado): RedirectResponse
    {
        $this->catalogo->actualizar($feriado, $request->validated(), 'feriado.actualizado');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Feriado «{$feriado->descripcion}» actualizado."]);

        return to_route('feriados.index', ['anio' => $feriado->fecha->year]);
    }

    public function destroy(Feriado $feriado): RedirectResponse
    {
        Gate::authorize('delete', $feriado);
        $this->catalogo->quitar($feriado, 'feriado.quitado');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Feriado «{$feriado->descripcion}» quitado."]);

        return to_route('feriados.index', ['anio' => $feriado->fecha->year]);
    }
}
