<?php

namespace App\Http\Controllers;

use App\Http\Requests\TipoDocumentoRequest;
use App\Models\TipoDocumento;
use App\Services\CatalogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TipoDocumentoController extends Controller
{
    public function __construct(private readonly CatalogoService $catalogo) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TipoDocumento::class);

        $filtros = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'estado' => ['nullable', 'in:activos,inactivos']]);

        $tipos = TipoDocumento::query()
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->whereLike('nombre', "%{$texto}%"))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('activo', $estado === 'activos'))
            ->orderBy('nombre')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('tipos-documento/Index', [
            'tipos' => JsonResource::collection($tipos->through(fn (TipoDocumento $t) => $this->fila($t))),
            'filtros' => (object) $filtros,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', TipoDocumento::class);

        return Inertia::render('tipos-documento/Form', ['tipo' => null]);
    }

    public function store(TipoDocumentoRequest $request): RedirectResponse
    {
        $tipo = $this->catalogo->crear(new TipoDocumento($request->validated()), 'tipo_documento.creado');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Tipo de documento «{$tipo->nombre}» creado."]);

        return to_route('tipos-documento.index');
    }

    public function edit(TipoDocumento $tipo): Response
    {
        Gate::authorize('update', $tipo);

        return Inertia::render('tipos-documento/Form', ['tipo' => $this->fila($tipo)]);
    }

    public function update(TipoDocumentoRequest $request, TipoDocumento $tipo): RedirectResponse
    {
        $this->catalogo->actualizar($tipo, $request->validated(), 'tipo_documento.actualizado');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Tipo de documento «{$tipo->nombre}» actualizado."]);

        return to_route('tipos-documento.index');
    }

    /** Forma espejada en resources/js/types/index.ts (TipoDocumento). */
    private function fila(TipoDocumento $t): array
    {
        return ['id' => $t->id, 'nombre' => $t->nombre, 'activo' => $t->activo, 'actualizado' => $t->updated_at?->toIso8601String()];
    }
}
