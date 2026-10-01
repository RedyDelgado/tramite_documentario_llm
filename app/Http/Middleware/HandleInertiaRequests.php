<?php

namespace App\Http\Middleware;

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
            ],
            // La interfaz solo oculta lo que no corresponde; quien autoriza es la Policy.
            'auth' => [
                'user' => $user?->only('id', 'name', 'email'),
                'roles' => fn () => $user?->getRoleNames()->all() ?? [],
                'can' => fn () => $user?->getAllPermissions()->pluck('name')->all() ?? [],
            ],
        ];
    }
}
