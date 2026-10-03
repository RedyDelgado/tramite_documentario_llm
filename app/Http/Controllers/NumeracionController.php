<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\TipoDocumento;
use App\Services\NumeracionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Con qué número continúa cada correlativo (pendiente 9): lo fija quien administra la configuración. */
class NumeracionController extends Controller
{
    public function __construct(private readonly NumeracionService $numeracion) {}

    public function index(Request $request): Response
    {
        Gate::authorize('configuracion.gestionar');
        $anio = (int) ($request->validate(['anio' => ['nullable', 'integer', 'min:2000', 'max:2100']])['anio'] ?? now()->year);

        return Inertia::render('numeracion/Index', [
            'correlativos' => $this->numeracion->lista($anio),
            'filtros' => (object) ['anio' => (string) $anio],
        ]);
    }

    /** El formulario se abre en un modal sobre la lista (CLAUDE.md), con el correlativo elegido si viene. */
    public function create(Request $request): Response
    {
        return $this->index($request)->with('formulario', [
            'opcionesTipoDocumento' => TipoDocumento::opciones(),
            'opcionesArea' => Area::opciones(),
            'inicial' => $request->only(['tipo', 'tipo_documento_id', 'area_id', 'anio', 'siguiente']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('configuracion.gestionar');
        $datos = $request->validate([
            'tipo' => ['required', Rule::in([NumeracionService::REGISTRO, 'saliente'])],
            'tipo_documento_id' => ['exclude_unless:tipo,saliente', 'required', 'integer', Rule::exists('tipos_documento', 'id')],
            'area_id' => ['exclude_unless:tipo,saliente', 'required', 'integer', Rule::exists('areas', 'id')],
            'anio' => ['required', 'integer', 'min:'.now()->year, 'max:'.(now()->year + 1)],
            'siguiente' => ['required', 'integer', 'min:1', 'max:99999'],
        ], attributes: ['tipo_documento_id' => 'tipo de documento', 'area_id' => 'área', 'anio' => 'año', 'siguiente' => 'siguiente número']);

        $this->numeracion->ajustar($datos);
        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "El siguiente número será el {$datos['siguiente']}."]);

        return to_route('numeracion.index', ['anio' => $datos['anio']]);
    }
}
