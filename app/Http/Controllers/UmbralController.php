<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Services\CatalogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Umbrales del semáforo (8); se leen en cada cálculo, sin desplegar. */
class UmbralController extends Controller
{
    public function __construct(private readonly CatalogoService $catalogo) {}

    public function edit(): Response
    {
        Gate::authorize('update', new Configuracion);

        $valores = Configuracion::todas();

        return Inertia::render('umbrales/Form', [
            'umbrales' => [
                'porcentaje_amarillo' => $valores['semaforo.porcentaje_amarillo'],
                'dias_sin_movimiento' => $valores['semaforo.dias_sin_movimiento'],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('update', new Configuracion);

        $datos = $request->validate([
            'porcentaje_amarillo' => ['required', 'integer', 'min:1', 'max:99'],
            'dias_sin_movimiento' => ['required', 'integer', 'min:1', 'max:365'],
        ], attributes: ['porcentaje_amarillo' => 'porcentaje restante', 'dias_sin_movimiento' => 'días sin movimiento']);

        $this->catalogo->guardarConfiguracion([
            'semaforo.porcentaje_amarillo' => (int) $datos['porcentaje_amarillo'],
            'semaforo.dias_sin_movimiento' => (int) $datos['dias_sin_movimiento'],
        ]);

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => 'Umbrales del semáforo actualizados.']);

        return back();
    }
}
