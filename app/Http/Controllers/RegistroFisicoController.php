<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistroFisicoRequest;
use App\Models\Emisor;
use App\Models\Expediente;
use App\Models\TipoDocumento;
use App\Services\RegistroFisicoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** Registro de documentos en papel (7.3): el administrativo sube el escaneo y confirma el formulario prellenado. */
class RegistroFisicoController extends Controller
{
    public function __construct(private readonly RegistroFisicoService $registro) {}

    public function create(): Response
    {
        Gate::authorize('create', Expediente::class);

        return Inertia::render('registro/Nuevo', [
            'opcionesEmisor' => Emisor::opciones(),
            'opcionesTipoDocumento' => TipoDocumento::opciones(),
        ]);
    }

    public function prellenar(Request $request): JsonResponse
    {
        Gate::authorize('create', Expediente::class);
        $archivo = $request->validate([
            'archivo' => ['required', 'file', 'max:40960', 'mimetypes:application/pdf,image/png,image/jpeg,image/tiff,image/webp'],
        ], attributes: ['archivo' => 'escaneo'])['archivo'];

        return response()->json($this->registro->prellenar($archivo));
    }

    public function store(RegistroFisicoRequest $request): RedirectResponse
    {
        $expediente = $this->registro->registrar($request->validated());

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Registrado como {$expediente->numero_registro} ({$expediente->codigo})."]);

        return to_route('expedientes.show', $expediente);
    }
}
