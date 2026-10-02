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
    private const ORDEN = ['nombre' => 'nombre', 'plazo' => 'plazo_dias'];

    public function __construct(private readonly CatalogoService $catalogo) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TipoTramite::class);

        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'estado' => ['nullable', 'in:activos,inactivos'],
            'orden' => ['nullable', 'in:'.implode(',', array_keys(self::ORDEN))],
            'dir' => ['nullable', 'in:asc,desc'],
        ]);

        $tipos = TipoTramite::query()
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->whereLike('nombre', "%{$texto}%"))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('activo', $estado === 'activos'))
            ->orderBy(self::ORDEN[$filtros['orden'] ?? 'nombre'], $filtros['dir'] ?? 'asc')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('tipos-tramite/Index', [
            'tipos' => TipoTramiteResource::collection($tipos),
            'filtros' => (object) $filtros,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', TipoTramite::class);

        return Inertia::render('tipos-tramite/Form', ['tipo' => null, ...$this->opciones()]);
    }

    public function store(TipoTramiteRequest $request): RedirectResponse
    {
        $tipo = $this->catalogo->crear(new TipoTramite($request->validated()), 'tipo_tramite.creado');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Tipo de trámite «{$tipo->nombre}» creado."]);

        return to_route('tipos-tramite.index');
    }

    public function edit(TipoTramite $tipo): Response
    {
        Gate::authorize('update', $tipo);

        return Inertia::render('tipos-tramite/Form', ['tipo' => TipoTramiteResource::make($tipo)->resolve(), ...$this->opciones()]);
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
