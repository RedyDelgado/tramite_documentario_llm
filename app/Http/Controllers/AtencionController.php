<?php

namespace App\Http\Controllers;

use App\Http\Requests\DerivarRequest;
use App\Models\Expediente;
use App\Services\AtencionService;
use App\Services\SerieService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** Derivación y atención de un expediente (7, 8); las reglas viven en AtencionService y ExpedientePolicy. */
class AtencionController extends Controller
{
    public function __construct(private readonly AtencionService $atencion) {}

    public function derivar(DerivarRequest $request, Expediente $expediente, SerieService $series): RedirectResponse
    {
        if ($request->boolean('toda_la_serie') && $expediente->grupo) {
            $total = $series->derivar($expediente->grupo, $request->validated(), $request->user());

            return $this->listo("Serie derivada: {$total} expedientes.");
        }
        $expediente = $this->atencion->derivar($expediente, $request->validated());

        return $this->listo("Derivado a {$expediente->area->nombre}.");
    }

    public function tomar(Expediente $expediente): RedirectResponse
    {
        Gate::authorize('atender', $expediente);
        $expediente = $this->atencion->tomar($expediente);

        return $this->listo($expediente->atendido_at ? 'Toma de conocimiento registrada: queda atendido.' : 'Tomado en atención.');
    }

    public function comentar(Request $request, Expediente $expediente): RedirectResponse
    {
        Gate::authorize('comentar', $expediente);
        $nota = $request->validate(['nota' => ['required', 'string', 'max:2000']])['nota'];
        $this->atencion->comentar($expediente, $nota);

        return $this->listo('Comentario agregado.');
    }

    public function solicitarCierre(Request $request, Expediente $expediente): RedirectResponse
    {
        Gate::authorize('atender', $expediente);
        $nota = $request->validate(['nota' => ['nullable', 'string', 'max:2000']])['nota'] ?? null;
        $expediente = $this->atencion->solicitarCierre($expediente, $nota);

        return $this->listo($expediente->cierre_solicitado_at ? 'Cierre solicitado; queda pendiente de aprobación.' : 'Expediente cerrado.');
    }

    public function resolverCierre(Request $request, Expediente $expediente): RedirectResponse
    {
        Gate::authorize('aprobarCierre', $expediente);
        $datos = $request->validate([
            'aprobar' => ['required', 'boolean'],
            'nota' => ['nullable', 'string', 'max:2000', 'required_if_declined:aprobar'],
        ], attributes: ['nota' => 'motivo']);
        $this->atencion->resolverCierre($expediente, (bool) $datos['aprobar'], $datos['nota'] ?? null);

        return $this->listo($datos['aprobar'] ? 'Expediente cerrado.' : 'Cierre rechazado; el expediente sigue en atención.');
    }

    private function listo(string $mensaje): RedirectResponse
    {
        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => $mensaje]);

        return back();
    }
}
