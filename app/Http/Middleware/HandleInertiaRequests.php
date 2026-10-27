<?php

namespace App\Http\Middleware;

use App\Models\Expediente;
use App\Services\BorradorIaService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /** Forma espejada en resources/js/types/inertia.d.ts (sharedPageProps). */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => [
                'nombre' => config('app.name'),
                'local' => app()->isLocal(),
                'salientes' => (bool) config('tramite.salientes.activo'),
                'ia' => BorradorIaService::activo(),
            ],
            // La interfaz solo oculta lo que no corresponde; quien autoriza es la Policy.
            'auth' => [
                'user' => $user?->only('id', 'name', 'email'),
                'roles' => fn () => $user?->getRoleNames()->all() ?? [],
                'can' => fn () => $user?->getAllPermissions()->pluck('name')->all() ?? [],
            ],
            // Quien deriva (administrativo y director) ve en la campana las respuestas de las áreas que aún no revisa.
            'respuestasNuevas' => fn () => $user?->can('expedientes.derivar')
                ? Expediente::visiblesPara($user)->conRespuestaNueva()->latest('id')->limit(10)->get(['id', 'secuencia', 'asunto'])
                    ->map(fn (Expediente $e) => $e->only('id', 'numero_registro', 'asunto'))
                : [],
        ];
    }
}
