<?php

namespace App\Http\Controllers;

use App\Http\Requests\DerivarRequest;
use App\Models\Correo;
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
        $mas = count($request->validated('areas')) - 1;

        return $this->listo("Derivado a {$expediente->area->nombre}".($mas ? " y {$mas} ".($mas === 1 ? 'área más' : 'áreas más') : '').'.');
    }

    public function tomar(Expediente $expediente): RedirectResponse
    {
        Gate::authorize('atender', $expediente);
        $expediente = $this->atencion->tomar($expediente);

        return $this->listo($expediente->atendido_at ? 'Toma de conocimiento registrada: queda atendido.' : 'Tomado en atención.');
    }

    /** Lo que el área hizo queda como nota: con eso el expediente se da por atendido cuando lo atendieron todas. */
    public function atender(Request $request, Expediente $expediente): RedirectResponse
    {
        Gate::authorize('atender', $expediente);
        $nota = $request->validate(['nota' => ['required', 'string', 'max:2000']], attributes: ['nota' => 'cómo se atendió'])['nota'];
        $expediente = $this->atencion->atender($expediente, $nota);

        return $this->listo($expediente->atendido_at ? 'Atendido: ya lo atendieron todas las áreas.' : 'Atendido por tu área; faltan otras áreas.');
    }

    /** Una persona del área confirma que el correo que llegó responde a lo pedido; la IA solo lo propuso. */
    public function atenderConCorreo(Correo $correo): RedirectResponse
    {
        abort_unless($correo->respuesta_area, 404);
        Gate::authorize('atender', $correo->expediente);
        $de = $correo->de_nombre ?? $correo->de_email;
        $expediente = $this->atencion->atender($correo->expediente, "Respondió por correo {$de} el {$correo->fecha->timezone('America/Lima')->format('d/m/Y H:i')}: «{$correo->asunto}»");

        return $this->listo($expediente->atendido_at ? 'Atendido con la respuesta por correo: ya lo atendieron todas las áreas.' : 'Atendido por tu área con la respuesta por correo; faltan otras áreas.');
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
