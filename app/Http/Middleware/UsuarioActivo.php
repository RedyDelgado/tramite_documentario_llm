<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Desactivar a un usuario corta su sesión abierta en la siguiente petición. */
class UsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->activo === false) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login');
        }

        return $next($request);
    }
}
