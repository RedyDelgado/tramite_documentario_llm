<?php

namespace App\Http\Controllers;

use App\Models\NotificacionEnviada;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Estado de entrega de los avisos del sistema: a quién, cuándo y si llegó; nunca el contenido de los trámites (5). */
class NotificacionController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('configuracion.gestionar');

        $filtros = $request->validate(['estado' => ['nullable', Rule::in(array_keys(NotificacionEnviada::ESTADOS))]]);

        $notificaciones = NotificacionEnviada::query()
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (NotificacionEnviada $n) => [
                'id' => $n->id,
                'fecha' => $n->created_at->toIso8601String(),
                'email' => $n->email,
                'tipo' => NotificacionEnviada::TIPOS[$n->tipo] ?? $n->tipo,
                'expedientes' => count($n->expedientes),
                'estado' => $n->estado,
                'detalle' => $n->detalle,
            ]);

        return Inertia::render('notificaciones/Index', [
            // Misma forma que los resources (data, links, meta): la que espera Paginado en el frontend.
            'notificaciones' => JsonResource::collection($notificaciones),
            'filtros' => (object) $filtros,
            'estados' => collect(NotificacionEnviada::ESTADOS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }
}
