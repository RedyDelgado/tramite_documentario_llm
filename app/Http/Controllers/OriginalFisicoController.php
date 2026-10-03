<?php

namespace App\Http\Controllers;

use App\Models\Expediente;
use App\Models\Movimiento;
use App\Rules\SinAmenazas;
use App\Services\AuditoriaService;
use App\Services\OriginalService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Original en papel (7.3.1): ubicación y custodio, constancia, etiqueta QR y cargo de entrega. */
class OriginalFisicoController extends Controller
{
    public function __construct(private readonly OriginalService $originales, private readonly AuditoriaService $auditoria) {}

    public function mover(Request $request, Expediente $expediente): RedirectResponse
    {
        Gate::authorize('custodiar', $expediente);
        $datos = $request->validate([
            'ubicacion_fisica_id' => ['nullable', 'integer', Rule::exists('ubicaciones_fisicas', 'id')->where('activa', true)],
            'custodio_id' => ['required', 'integer', Rule::exists('users', 'id')->where('activo', true)],
            'nota' => ['nullable', 'string', 'max:500'],
        ], attributes: ['ubicacion_fisica_id' => 'ubicación', 'custodio_id' => 'custodio']);
        $this->originales->mover($expediente, $datos['ubicacion_fisica_id'] ?? null, $datos['custodio_id'], $datos['nota'] ?? null);

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => 'Ubicación del original actualizada.']);

        return back();
    }

    public function constancia(Expediente $expediente): View
    {
        return $this->imprimir('constancia', $expediente, 'constancia.impresa');
    }

    public function etiqueta(Expediente $expediente): View
    {
        return $this->imprimir('etiqueta', $expediente, 'etiqueta.impresa');
    }

    public function cargo(Movimiento $movimiento): View
    {
        abort_unless($movimiento->tipo === 'derivacion', 404);
        $movimiento->load(['aArea', 'aUser', 'user']);

        return $this->imprimir('cargo', $movimiento->expediente, 'cargo.impreso', ['movimiento' => $movimiento]);
    }

    public function adjuntarCargo(Request $request, Movimiento $movimiento): RedirectResponse
    {
        Gate::authorize('custodiar', $movimiento->expediente);
        $archivo = $request->validate([
            'archivo' => ['required', 'file', 'max:40960', 'mimetypes:application/pdf,image/png,image/jpeg,image/tiff,image/webp', new SinAmenazas],
        ], attributes: ['archivo' => 'cargo firmado'])['archivo'];
        $this->originales->adjuntarCargo($movimiento, $archivo);

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => 'Cargo firmado adjuntado.']);

        return back();
    }

    /** Toda impresión queda auditada (9). */
    private function imprimir(string $vista, Expediente $expediente, string $accion, array $extra = []): View
    {
        Gate::authorize('custodiar', $expediente);
        abort_unless($expediente->codigo, 404, 'El expediente aún no tiene número de registro.');
        $this->auditoria->registrar($accion, $expediente, despues: isset($extra['movimiento']) ? ['movimiento_id' => $extra['movimiento']->id] : null);
        $expediente->load(['emisor', 'tipoDocumento', 'registrador', 'ubicacionFisica', 'custodio']);

        // Px del QR según el espacio de cada formato.
        $tamano = ['constancia' => 160, 'cargo' => 128, 'etiqueta' => 112][$vista];

        return view("impresion.{$vista}", ['e' => $expediente, 'qr' => OriginalService::qr($expediente, $tamano), ...$extra]);
    }
}
