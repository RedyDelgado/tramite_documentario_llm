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
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    /** Columnas ordenables expuestas a la tabla => columna real. */
    private const ORDEN = ['nombre' => 'name', 'correo' => 'email', 'actualizado' => 'updated_at'];

    public function __construct(private readonly UsuarioService $usuarios) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'rol' => ['nullable', Rule::in(array_keys(RolesSeeder::ROLES))],
            'estado' => ['nullable', 'in:activos,inactivos'],
            'orden' => ['nullable', 'in:'.implode(',', array_keys(self::ORDEN))],
            'dir' => ['nullable', 'in:asc,desc'],
        ]);

        $usuarios = User::query()
            ->with('roles:id,name')
            ->when($filtros['q'] ?? null, fn ($q, $texto) => $q->where(fn ($q) => $q
                ->whereLike('name', "%{$texto}%")
                ->orWhereLike('email', "%{$texto}%")))
            ->when($filtros['rol'] ?? null, fn ($q, $rol) => $q->role($rol))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('activo', $estado === 'activos'))
            ->orderBy(self::ORDEN[$filtros['orden'] ?? 'nombre'], $filtros['dir'] ?? 'asc')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('usuarios/Index', [
            'usuarios' => UsuarioResource::collection($usuarios),
            'filtros' => (object) $filtros,
            'opcionesRol' => $this->opcionesRol(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('usuarios/Form', $this->datosFormulario(null));
    }

    public function store(UsuarioRequest $request): RedirectResponse
    {
        $usuario = $this->usuarios->crear($request->validated());

        Inertia::flash('toast', ['tipo' => 'ok', 'mensaje' => "Usuario «{$usuario->name}» creado."]);

        return to_route('usuarios.index');
    }

    public function edit(User $usuario): Response
    {
        Gate::authorize('update', $usuario);

        return Inertia::render('usuarios/Form', $this->datosFormulario($usuario));
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
            'usuario' => $usuario ? UsuarioResource::make($usuario->load('roles:id,name'))->resolve() : null,
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
