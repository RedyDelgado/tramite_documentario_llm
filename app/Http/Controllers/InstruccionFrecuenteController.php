<?php

namespace App\Http\Controllers;

use App\Http\Requests\InstruccionFrecuenteRequest;
use App\Models\InstruccionFrecuente;
use App\Services\CatalogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InstruccionFrecuenteController extends Controller
{
    public function __construct(private readonly CatalogoService $catalogo) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', InstruccionFrecuente::class);

        $filtros = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'estado' => ['nullable', 'in:activas,inactivas']]);

        $instrucciones = InstruccionFrecuente::query()
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->whereLike('texto', "%{$texto}%"))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('activa', $estado === 'activas'))
            ->orderBy('orden')
            ->orderBy('texto')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('instrucciones/Index', [
            'instrucciones' => JsonResource::collection($instrucciones->through(fn (InstruccionFrecuente $i) => $this->fila($i))),
            'filtros' => (object) $filtros,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', InstruccionFrecuente::class);

        return $this->index($request)->with('formulario', ['instruccion' => null]);
    }

    public function store(InstruccionFrecuenteRequest $request): RedirectResponse
    {
        $this->catalogo->crear(new InstruccionFrecuente($request->validated()), 'instruccion.creada');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => 'Instrucción creada.']);

        return to_route('instrucciones.index');
    }

    public function edit(Request $request, InstruccionFrecuente $instruccion): Response
    {
        Gate::authorize('update', $instruccion);

        return $this->index($request)->with('formulario', ['instruccion' => $this->fila($instruccion)]);
    }

    public function update(InstruccionFrecuenteRequest $request, InstruccionFrecuente $instruccion): RedirectResponse
    {
        $this->catalogo->actualizar($instruccion, $request->validated(), 'instruccion.actualizada');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => 'Instrucción actualizada.']);

        return to_route('instrucciones.index');
    }

    /** Forma espejada en resources/js/types/index.ts (InstruccionFrecuente). */
    private function fila(InstruccionFrecuente $i): array
    {
        return ['id' => $i->id, 'texto' => $i->texto, 'orden' => $i->orden, 'activa' => $i->activa, 'actualizado' => $i->updated_at?->toIso8601String()];
    }
}
