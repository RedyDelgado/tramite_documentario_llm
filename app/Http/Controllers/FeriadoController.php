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
        // Catálogo chico: va completo y se busca, filtra y pagina en el navegador (useListaLocal).
        Gate::authorize('viewAny', Feriado::class);

        $feriados = Feriado::query()
            ->with('area:id,nombre')
            ->orderBy('fecha')
            ->get();

        return Inertia::render('feriados/Index', [
            'feriados' => FeriadoResource::collection($feriados)->resolve($request),
            'opcionesArea' => Area::opciones(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Feriado::class);

        return $this->index($request)->with('formulario', ['feriado' => null, 'opcionesArea' => Area::opciones()]);
    }

    public function store(FeriadoRequest $request): RedirectResponse
    {
        $feriado = $this->catalogo->crear(new Feriado($request->validated()), 'feriado.creado');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Feriado «{$feriado->descripcion}» registrado."]);

        return to_route('feriados.index', ['anio' => $feriado->fecha->year]);
    }

    public function edit(Request $request, Feriado $feriado): Response
    {
        Gate::authorize('update', $feriado);

        return $this->index($request)->with('formulario', ['feriado' => FeriadoResource::make($feriado)->resolve(), 'opcionesArea' => Area::opciones()]);
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
