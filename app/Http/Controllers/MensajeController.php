<?php

namespace App\Http\Controllers;

use App\Models\Conversacion;
use App\Models\Mensaje;
use App\Models\User;
use App\Services\MensajeriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MensajeController extends Controller
{
    // Lo que se carga de una conversación al abrirla; lo anterior queda guardado, pero no se trae.
    private const MENSAJES_VISIBLES = 200;

    public function __construct(private readonly MensajeriaService $mensajeria) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $activa = null;
        $mensajes = [];

        if ($id = $request->integer('c')) {
            $conversacion = Conversacion::with('participantes:id,name')->findOrFail($id);
            Gate::authorize('view', $conversacion);
            // Abrirla la deja leída; la recarga periódica de la página también, mientras siga abierta.
            $this->mensajeria->marcarLeido($user, $conversacion);
            $activa = $this->resumen($conversacion, $user);
            $mensajes = $conversacion->mensajes()->with('autor:id,name')->latest('id')->limit(self::MENSAJES_VISIBLES)->get()->reverse()->values()
                ->map(fn (Mensaje $m) => [
                    'id' => $m->id,
                    'texto' => $m->texto,
                    'autor' => $m->autor->name,
                    'propio' => $m->user_id === $user->id,
                    'enviado_at' => $m->created_at->toIso8601String(),
                ]);
        }

        $conversaciones = Conversacion::de($user)
            ->with('participantes:id,name')
            ->addSelect(['ultimo_texto' => Mensaje::select('texto')->whereColumn('conversacion_id', 'conversaciones.id')->latest('id')->limit(1)])
            ->withCount(['mensajes as sin_leer' => fn ($q) => $q->where('user_id', '!=', $user->id)->whereRaw(
                'mensajes.id > coalesce((select leido_hasta from conversacion_usuario where conversacion_id = conversaciones.id and user_id = ?), 0)',
                [$user->id],
            )])
            ->orderByRaw('coalesce(ultimo_mensaje_at, created_at) desc')
            ->get()
            ->map(fn (Conversacion $c) => [
                ...$this->resumen($c, $user),
                'ultimo_texto' => $c->ultimo_texto ? mb_strimwidth($c->ultimo_texto, 0, 80, '…') : null,
                'ultimo_mensaje_at' => $c->ultimo_mensaje_at?->toIso8601String(),
                'sin_leer' => $c->sin_leer,
            ]);

        return Inertia::render('mensajes/Index', [
            'conversaciones' => $conversaciones,
            'activa' => $activa,
            'mensajes' => $mensajes,
            'usuarios' => fn () => collect(User::opciones())->reject(fn (array $o) => $o['value'] === $user->id)->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Conversacion::class);
        $datos = $request->validate([
            'usuarios' => ['required', 'array', 'min:1', 'max:50'],
            'usuarios.*' => ['integer'],
            'nombre' => ['nullable', 'string', 'max:80'],
        ]);

        $conversacion = $this->mensajeria->abrir($request->user(), $datos['usuarios'], $datos['nombre'] ?? null);

        return to_route('mensajes.index', ['c' => $conversacion->id]);
    }

    public function enviar(Request $request, Conversacion $conversacion): RedirectResponse
    {
        Gate::authorize('enviar', $conversacion);
        $datos = $request->validate(['texto' => ['required', 'string', 'max:2000']]);

        $this->mensajeria->enviar($request->user(), $conversacion, $datos['texto']);

        return to_route('mensajes.index', ['c' => $conversacion->id]);
    }

    /** Para el contador del menú, que se consulta cada cierto tiempo sin recargar la página. */
    public function sinLeer(Request $request): JsonResponse
    {
        return response()->json(['total' => $this->mensajeria->sinLeer($request->user())]);
    }

    /** @return array{id: int, titulo: string, grupo: bool, participantes: list<string>} */
    private function resumen(Conversacion $c, User $user): array
    {
        $otros = $c->participantes->reject(fn (User $u) => $u->id === $user->id)->pluck('name')->values()->all();

        return [
            'id' => $c->id,
            // Un grupo sin nombre se reconoce por quiénes están.
            'titulo' => $c->nombre ?? (implode(', ', $otros) ?: 'Solo tú'),
            'grupo' => $c->esGrupo(),
            'participantes' => $otros,
        ];
    }
}
