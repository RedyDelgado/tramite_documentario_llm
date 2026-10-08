<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmisorRequest;
use App\Models\Emisor;
use App\Services\CatalogoService;
use App\Services\EmisorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmisorController extends Controller
{
    public function __construct(private readonly CatalogoService $catalogo, private readonly EmisorService $emisores) {}

    public function index(Request $request): Response
    {
        // ponytail: catálogo completo al navegador; si los emisores pasan de unos miles, buscar en el servidor.
        // Catálogo chico: va completo y se busca, filtra y pagina en el navegador (useListaLocal).
        Gate::authorize('viewAny', Emisor::class);

        $emisores = Emisor::query()
            ->whereNull('fusionado_en_id')
            ->withCount('expedientes')
            ->orderBy('nombre')
            ->get();

        return Inertia::render('emisores/Index', [
            'emisores' => $emisores->map(fn (Emisor $e) => $this->fila($e))->values(),
            'duplicados' => Emisor::posiblesDuplicados(),
            'opcionesEmisor' => Emisor::opciones(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Emisor::class);

        return $this->index($request)->with('formulario', ['emisor' => null, 'opcionesTipo' => $this->opcionesTipo()]);
    }

    public function store(EmisorRequest $request): RedirectResponse
    {
        $emisor = $this->catalogo->crear(new Emisor($request->datos()), 'emisor.creado');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Emisor «{$emisor->nombre}» creado."]);

        return to_route('emisores.index');
    }

    /** Alta en línea desde el registro: responde la opción para el Combobox. */
    public function rapido(EmisorRequest $request): JsonResponse
    {
        $emisor = $this->catalogo->crear(new Emisor($request->datos()), 'emisor.creado');

        return response()->json(['value' => $emisor->id, 'label' => $emisor->nombre], 201);
    }

    public function edit(Request $request, Emisor $emisor): Response
    {
        Gate::authorize('update', $emisor);

        return $this->index($request)->with('formulario', ['emisor' => $this->fila($emisor), 'opcionesTipo' => $this->opcionesTipo()]);
    }

    public function update(EmisorRequest $request, Emisor $emisor): RedirectResponse
    {
        $this->catalogo->actualizar($emisor, $request->datos(), 'emisor.actualizado');

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Emisor «{$emisor->nombre}» actualizado."]);

        return to_route('emisores.index');
    }

    public function fusionar(Request $request, Emisor $emisor): RedirectResponse
    {
        Gate::authorize('update', $emisor);

        $destino = Emisor::findOrFail($request->validate(['destino_id' => ['required', 'integer']])['destino_id']);
        $movidos = $this->emisores->fusionar($emisor, $destino);

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "«{$emisor->nombre}» se fusionó en «{$destino->nombre}»: {$movidos} expedientes reasignados."]);

        return back();
    }

    /** Forma espejada en resources/js/types/index.ts (Emisor). */
    private function fila(Emisor $e): array
    {
        return [
            'id' => $e->id,
            'nombre' => $e->nombre,
            'tipo' => $e->tipo,
            'activo' => $e->activo,
            'expedientes' => $e->expedientes_count ?? null,
            'actualizado' => $e->updated_at?->toIso8601String(),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function opcionesTipo(): array
    {
        return collect(Emisor::TIPOS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }
}
