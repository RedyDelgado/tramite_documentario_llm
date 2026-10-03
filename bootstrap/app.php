<?php

use App\Exceptions\ReglaDeNegocio;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\UsuarioActivo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            UsuarioActivo::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
        $middleware->redirectUsersTo(fn () => route('inicio'));
        // Detrás de Caddy o del proxy HTTPS de la institución: la IP del usuario (auditoría) y el https salen de X-Forwarded-*.
        // Solo desde redes privadas: desde internet nadie puede suplantarlas, y nginx solo escucha en 127.0.0.1.
        $middleware->trustProxies(at: ['127.0.0.1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Una regla de negocio incumplida vuelve a la pantalla con su mensaje como aviso.
        $exceptions->render(function (ReglaDeNegocio $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            Inertia::flash('toast', ['tipo' => 'error', 'mensaje' => $e->getMessage()]);

            return back();
        });
    })->create();
