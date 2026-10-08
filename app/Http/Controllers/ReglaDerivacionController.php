<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReglaDerivacionRequest;
use App\Http\Resources\ReglaDerivacionResource;
use App\Models\Area;
use App\Models\ReglaDerivacion;
use App\Models\TipoTramite;
use App\Models\User;
use App\Services\CatalogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ReglaDerivacionController extends Controller
{
    private const RELACIONES = ['tipoTramite:id,nombre', 'areaDestino:id,nombre', 'responsable:id,name'];

    public function __construct(private readonly CatalogoService $catalogo) {}

    public function index(Request $request): Response
    {
        // Catálogo chico: va completo y se busca, filtra y pagina en el navegador (useListaLocal).
        Gate::authorize('viewAny', ReglaDerivacion::class);

        $reglas = ReglaDerivacion::query()
            ->with(self::RELACIONES)
            ->orderBy('prioridad')
            ->orderBy('id')
            ->get();

        return Inertia::render('reglas-derivacion/Index', [
            'reglas' => ReglaDerivacionResource::collection($reglas)->resolve($request),
            'opcionesArea' => Area::opciones(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', ReglaDerivacion::class);

        return $this->index($request)->with('formulario', ['regla' => null, ...$this->opciones()]);
    }

    public function store(ReglaDerivacionRequest $request): RedirectResponse
    {
        $regla = $this->catalogo->crear(new ReglaDerivacion($request->datos()), 'regla_derivacion.creada');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Regla «{$regla->nombre}» creada."]);

        return to_route('reglas-derivacion.index');
    }

    public function edit(Request $request, ReglaDerivacion $regla): Response
    {
        Gate::authorize('update', $regla);

        return $this->index($request)->with('formulario', [
            'regla' => ReglaDerivacionResource::make($regla->load(self::RELACIONES))->resolve(),
            ...$this->opciones(),
        ]);
    }

    public function update(ReglaDerivacionRequest $request, ReglaDerivacion $regla): RedirectResponse
    {
        $this->catalogo->actualizar($regla, $request->datos(), 'regla_derivacion.actualizada');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Regla «{$regla->nombre}» actualizada."]);

        return to_route('reglas-derivacion.index');
    }

    public function cambiarEstado(Request $request, ReglaDerivacion $regla): RedirectResponse
    {
        Gate::authorize('update', $regla);

        $activa = (bool) $request->validate(['activa' => ['required', 'boolean']])['activa'];
        $this->catalogo->actualizar($regla, ['activa' => $activa], $activa ? 'regla_derivacion.activada' : 'regla_derivacion.desactivada');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Regla «{$regla->nombre}» ".($activa ? 'activada.' : 'desactivada.')]);

        return back();
    }

    /** @return array<string, list<array{value: int, label: string}>> */
    private function opciones(): array
    {
        return ['opcionesTipo' => TipoTramite::opciones(), 'opcionesArea' => Area::opciones(), 'opcionesUsuario' => User::opciones()];
    }
}
