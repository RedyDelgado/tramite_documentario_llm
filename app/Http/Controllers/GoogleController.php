<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaDeNegocio;
use App\Services\AccesoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as RedireccionExterna;

class GoogleController extends Controller
{
    public static function habilitado(): bool
    {
        return filled(config('tramite.google_dominio')) && filled(config('services.google.client_id'));
    }

    public function redirigir(): RedireccionExterna
    {
        abort_unless(self::habilitado(), 404);

        // `hd` solo preselecciona la cuenta del dominio; la verificación real está en AccesoService.
        return Socialite::driver('google')->with(['hd' => config('tramite.google_dominio'), 'prompt' => 'select_account'])->redirect();
    }

    public function volver(Request $request, AccesoService $acceso): RedirectResponse
    {
        abort_unless(self::habilitado(), 404);

        try {
            $acceso->iniciarConGoogle(Socialite::driver('google')->user());
        } catch (ReglaDeNegocio $e) {
            return $this->aLogin($e->getMessage());
        } catch (InvalidStateException) {
            return $this->aLogin('La sesión con Google expiró. Vuelve a intentarlo.');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('inicio'));
    }

    private function aLogin(string $mensaje): RedirectResponse
    {
        Inertia::flash('toast', ['tipo' => 'error', 'mensaje' => $mensaje]);

        return to_route('login');
    }
}
