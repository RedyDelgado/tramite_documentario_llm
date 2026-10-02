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
        Gate::authorize('viewAny', Emisor::class);

        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'tipo' => ['nullable', 'in:'.implode(',', array_keys(Emisor::TIPOS))],
            'estado' => ['nullable', 'in:activos,inactivos'],
        ]);

        $emisores = Emisor::query()
            ->whereNull('fusionado_en_id')
            ->withCount('expedientes')
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->whereLike('nombre_normalizado', '%'.Emisor::normalizar($texto).'%'))
            ->when($filtros['tipo'] ?? null, fn ($q, $tipo) => $q->where('tipo', $tipo))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('activo', $estado === 'activos'))
            ->orderBy('nombre')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('emisores/Index', [
            'emisores' => $emisores->through(fn (Emisor $e) => $this->fila($e)),
            'filtros' => (object) $filtros,
            'duplicados' => Emisor::posiblesDuplicados(),
            'opcionesEmisor' => Emisor::opciones(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Emisor::class);

        return Inertia::render('emisores/Form', ['emisor' => null, 'opcionesTipo' => $this->opcionesTipo()]);
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

    public function edit(Emisor $emisor): Response
    {
        Gate::authorize('update', $emisor);

        return Inertia::render('emisores/Form', ['emisor' => $this->fila($emisor), 'opcionesTipo' => $this->opcionesTipo()]);
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
