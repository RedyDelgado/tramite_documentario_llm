<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use App\Models\Grupo;
use App\Services\SerieService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** Series de documentos relacionados (7.3.1). */
class SerieController extends Controller
{
    public function __construct(private readonly SerieService $series) {}

    public function agrupar(Request $request, Expediente $expediente): RedirectResponse
    {
        Gate::authorize('agrupar', $expediente);
        $datos = $request->validate([
            'grupo_id' => ['nullable', 'integer', 'exists:grupos,id'],
            'nombre' => ['required_without:grupo_id', 'nullable', 'string', 'max:200'],
            'incluir' => ['array', 'max:50'],
            'incluir.*' => ['integer'],
        ], attributes: ['nombre' => 'nombre de la serie', 'grupo_id' => 'serie']);

        $grupo = $this->series->agrupar(
            [$expediente->id, ...($datos['incluir'] ?? [])],
            isset($datos['grupo_id']) ? Grupo::find($datos['grupo_id']) : null,
            $datos['nombre'] ?? null,
            $request->user(),
        );

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Agrupado en la serie «{$grupo->nombre}»."]);

        return back();
    }

    public function quitar(Expediente $expediente): RedirectResponse
    {
        Gate::authorize('agrupar', $expediente);
        $this->series->quitar($expediente);

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => 'Quitado de la serie.']);

        return back();
    }
}
