<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioRequest;
use App\Http\Resources\UsuarioResource;
use App\Models\User;
use App\Services\UsuarioService;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    /** Columnas ordenables expuestas a la tabla => columna real. */
    public function __construct(private readonly UsuarioService $usuarios) {}

    public function index(Request $request): Response
    {
        // Catálogo chico: va completo y se busca, filtra y pagina en el navegador (useListaLocal).
        Gate::authorize('viewAny', User::class);

        $usuarios = User::query()
            ->with(['roles:id,name', 'roles.permissions:id,name', 'permissions:id,name'])
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return Inertia::render('usuarios/Index', [
            'usuarios' => UsuarioResource::collection($usuarios)->resolve($request),
            'opcionesRol' => $this->opcionesRol(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', User::class);

        return $this->index($request)->with('formulario', $this->datosFormulario(null));
    }

    public function store(UsuarioRequest $request): RedirectResponse
    {
        $usuario = $this->usuarios->crear($request->validated());

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Usuario «{$usuario->name}» creado."]);

        return to_route('usuarios.index');
    }

    public function edit(Request $request, User $usuario): Response
    {
        Gate::authorize('update', $usuario);

        return $this->index($request)->with('formulario', $this->datosFormulario($usuario));
    }

    public function update(UsuarioRequest $request, User $usuario): RedirectResponse
    {
        $this->usuarios->actualizar($usuario, $request->validated());

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Usuario «{$usuario->name}» actualizado."]);

        return to_route('usuarios.index');
    }

    public function cambiarEstado(Request $request, User $usuario): RedirectResponse
    {
        Gate::authorize('update', $usuario);

        $activo = (bool) $request->validate(['activo' => ['required', 'boolean']])['activo'];
        $this->usuarios->cambiarEstado($usuario, $activo);

        Inertia::flash('toast', [
            'tipo' => 'ok',
            'mensaje' => $activo ? "Usuario «{$usuario->name}» activado." : "Usuario «{$usuario->name}» desactivado.",
        ]);

        return back();
    }

    /** @return array<string, mixed> */
    private function datosFormulario(?User $usuario): array
    {
        return [
            // resolve(): el recurso suelto va sin el envoltorio `data`.
            'usuario' => $usuario ? UsuarioResource::make($usuario->load(['roles:id,name', 'roles.permissions:id,name', 'permissions:id,name']))->resolve() : null,
            'opcionesRol' => $this->opcionesRol(),
            'dominio' => config('tramite.google_dominio'),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function opcionesRol(): array
    {
        return collect(RolesSeeder::ROLES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }
}
