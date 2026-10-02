<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReglaNoTramiteRequest;
use App\Models\ReglaNoTramite;
use App\Services\CatalogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Reglas que archivan un correo como no trámite al ingresar (7.3.2); no reclasifican lo ya ingresado. */
class ReglaNoTramiteController extends Controller
{
    public function __construct(private readonly CatalogoService $catalogo) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ReglaNoTramite::class);

        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'campo' => ['nullable', 'in:'.implode(',', array_keys(ReglaNoTramite::CAMPOS))],
            'estado' => ['nullable', 'in:activas,inactivas'],
        ]);

        $reglas = ReglaNoTramite::query()
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->where(fn ($q) => $q->whereLike('nombre', "%{$texto}%")->orWhereLike('valor', "%{$texto}%")))
            ->when($filtros['campo'] ?? null, fn ($q, $campo) => $q->where('campo', $campo))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('activa', $estado === 'activas'))
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('reglas-no-tramite/Index', [
            'reglas' => JsonResource::collection($reglas->through(fn (ReglaNoTramite $r) => $this->fila($r))),
            'filtros' => (object) $filtros,
            'opcionesCampo' => $this->opcionesCampo(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', ReglaNoTramite::class);

        return $this->index($request)->with('formulario', ['regla' => null, 'opcionesCampo' => $this->opcionesCampo()]);
    }

    public function store(ReglaNoTramiteRequest $request): RedirectResponse
    {
        $regla = $this->catalogo->crear(new ReglaNoTramite($request->datos()), 'regla_no_tramite.creada');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Regla «{$regla->nombre}» creada."]);

        return to_route('reglas-no-tramite.index');
    }

    public function edit(Request $request, ReglaNoTramite $regla): Response
    {
        Gate::authorize('update', $regla);

        return $this->index($request)->with('formulario', ['regla' => $this->fila($regla), 'opcionesCampo' => $this->opcionesCampo()]);
    }

    public function update(ReglaNoTramiteRequest $request, ReglaNoTramite $regla): RedirectResponse
    {
        $this->catalogo->actualizar($regla, $request->datos(), 'regla_no_tramite.actualizada');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Regla «{$regla->nombre}» actualizada."]);

        return to_route('reglas-no-tramite.index');
    }

    /** Forma espejada en resources/js/types/index.ts (ReglaNoTramite). */
    private function fila(ReglaNoTramite $r): array
    {
        return [
            'id' => $r->id,
            'nombre' => $r->nombre,
            'campo' => $r->campo,
            'campo_etiqueta' => ReglaNoTramite::CAMPOS[$r->campo] ?? $r->campo,
            'valor' => $r->valor,
            'activa' => $r->activa,
            'actualizado' => $r->updated_at?->toIso8601String(),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function opcionesCampo(): array
    {
        return collect(ReglaNoTramite::CAMPOS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }
}
