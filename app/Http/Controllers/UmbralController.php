<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Services\CatalogoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Umbrales del semáforo (8) y de la IA (10); se leen en cada cálculo, sin desplegar. */
class UmbralController extends Controller
{
    public function __construct(private readonly CatalogoService $catalogo) {}

    public function edit(): Response
    {
        Gate::authorize('update', new Configuracion);

        $valores = Configuracion::todas();

        return Inertia::render('umbrales/Index', [
            'umbrales' => [
                'porcentaje_amarillo' => $valores['semaforo.porcentaje_amarillo'],
                'dias_sin_movimiento' => $valores['semaforo.dias_sin_movimiento'],
                'ia_modo' => $valores['ia.modo'],
                'ia_umbral_sugerencia' => $valores['ia.umbral_sugerencia'],
                'ia_umbral_alta' => $valores['ia.umbral_alta'],
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('update', new Configuracion);

        $datos = $request->validate([
            'porcentaje_amarillo' => ['required', 'integer', 'min:1', 'max:99'],
            'dias_sin_movimiento' => ['required', 'integer', 'min:1', 'max:365'],
            'ia_modo' => ['required', 'in:sombra,activo'],
            'ia_umbral_sugerencia' => ['required', 'numeric', 'min:0.05', 'max:0.99'],
            'ia_umbral_alta' => ['required', 'numeric', 'gte:ia_umbral_sugerencia', 'max:0.99'],
        ], attributes: [
            'porcentaje_amarillo' => 'porcentaje restante', 'dias_sin_movimiento' => 'días sin movimiento',
            'ia_modo' => 'modo de la IA', 'ia_umbral_sugerencia' => 'umbral de propuesta', 'ia_umbral_alta' => 'umbral de alta confianza',
        ]);

        $this->catalogo->guardarConfiguracion([
            'semaforo.porcentaje_amarillo' => (int) $datos['porcentaje_amarillo'],
            'semaforo.dias_sin_movimiento' => (int) $datos['dias_sin_movimiento'],
            'ia.modo' => $datos['ia_modo'],
            'ia.umbral_sugerencia' => round((float) $datos['ia_umbral_sugerencia'], 2),
            'ia.umbral_alta' => round((float) $datos['ia_umbral_alta'], 2),
        ]);

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => 'Umbrales actualizados.']);

        return back();
    }
}
