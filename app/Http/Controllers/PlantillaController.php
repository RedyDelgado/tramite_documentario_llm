<?php

namespace App\Http\Controllers;

use App\Models\PlantillaDocumento;
use App\Models\TipoDocumento;
use App\Services\CatalogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Plantillas de documentos salientes, editables desde el panel (7.3.4). */
class PlantillaController extends Controller
{
    public function __construct(private readonly CatalogoService $catalogo) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', PlantillaDocumento::class);

        $plantillas = PlantillaDocumento::with('tipoDocumento:id,nombre')->orderBy('nombre')->paginate(25);

        return Inertia::render('plantillas/Index', [
            'plantillas' => JsonResource::collection($plantillas->through(fn (PlantillaDocumento $p) => $this->fila($p))),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', PlantillaDocumento::class);

        return $this->index($request)->with('formulario', ['plantilla' => null, ...$this->opciones()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', PlantillaDocumento::class);
        $this->catalogo->crear(new PlantillaDocumento($this->validar($request)), 'plantilla.creada');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => 'Plantilla creada.']);

        return to_route('plantillas.index');
    }

    public function edit(Request $request, PlantillaDocumento $plantilla): Response
    {
        Gate::authorize('update', $plantilla);

        return $this->index($request)->with('formulario', ['plantilla' => [...$this->fila($plantilla), 'asunto' => $plantilla->asunto, 'cuerpo' => $plantilla->cuerpo], ...$this->opciones()]);
    }

    public function update(Request $request, PlantillaDocumento $plantilla): RedirectResponse
    {
        Gate::authorize('update', $plantilla);
        $this->catalogo->actualizar($plantilla, $this->validar($request, $plantilla), 'plantilla.actualizada');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => 'Plantilla actualizada. Los documentos ya redactados no cambian.']);

        return to_route('plantillas.index');
    }

    /** @return array<string, mixed> */
    private function validar(Request $request, ?PlantillaDocumento $plantilla = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:150', Rule::unique('plantillas_documento', 'nombre')->ignore($plantilla)],
            'tipo_documento_id' => ['required', 'integer', Rule::exists('tipos_documento', 'id')->where('activo', true)],
            'asunto' => ['required', 'string', 'max:300'],
            'cuerpo' => ['required', 'string', 'max:20000'],
            'activa' => ['required', 'boolean'],
        ], attributes: ['tipo_documento_id' => 'tipo de documento']);
    }

    private function fila(PlantillaDocumento $p): array
    {
        return [
            'id' => $p->id, 'nombre' => $p->nombre, 'tipo_documento_id' => $p->tipo_documento_id, 'tipo' => $p->tipoDocumento?->nombre,
            'activa' => $p->activa, 'actualizado' => $p->updated_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function opciones(): array
    {
        return [
            'opcionesTipo' => TipoDocumento::opciones(),
            'variables' => collect(PlantillaDocumento::VARIABLES)->map(fn ($descripcion, $variable) => ['variable' => $variable, 'descripcion' => $descripcion])->values(),
        ];
    }
}
