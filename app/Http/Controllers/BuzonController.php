<?php

namespace App\Http\Controllers;

use App\Models\Buzon;
use App\Services\BuzonService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as RedireccionExterna;

/** Buzón central (7.1) desde el panel: una o varias cuentas de Google, descargar desde una fecha y la descarga automática. Solo el superadmin. */
class BuzonController extends Controller
{
    // Leer, etiquetar lo procesado y enviar desde el buzón (7.1, 7.3.4).
    private const PERMISO_GMAIL = 'https://www.googleapis.com/auth/gmail.modify';

    public function __construct(private readonly BuzonService $buzon) {}

    public function index(): Response
    {
        Gate::authorize('gestionar-buzon');

        return Inertia::render('buzon/Index', ['buzon' => $this->buzon->estado()]);
    }

    /** A Google, con el permiso de Gmail y acceso permanente (refresh token): se elige la cuenta que se agrega. */
    public function conectar(): RedireccionExterna
    {
        Gate::authorize('gestionar-buzon');

        return Socialite::driver('google')
            ->redirectUrl($this->buzon->redireccion())
            ->scopes([self::PERMISO_GMAIL])
            ->with(['access_type' => 'offline', 'prompt' => 'consent select_account'])
            ->redirect();
    }

    public function volver(): RedirectResponse
    {
        Gate::authorize('gestionar-buzon');

        try {
            $cuenta = Socialite::driver('google')->redirectUrl($this->buzon->redireccion())->user();
        } catch (InvalidStateException) {
            return $this->aviso('error', 'La conexión con Google expiró. Vuelve a intentarlo.');
        }
        if (! in_array(self::PERMISO_GMAIL, $cuenta->approvedScopes ?? [], true)) {
            return $this->aviso('error', 'No se concedió el acceso a Gmail: al conectar, marca la casilla de Gmail.');
        }
        if (! $cuenta->refreshToken) {
            return $this->aviso('error', 'Google no entregó el acceso permanente. Quita el acceso de la app en tu cuenta de Google y vuelve a conectar.');
        }

        $buzon = $this->buzon->conectar((string) $cuenta->getEmail(), $cuenta->refreshToken);

        return $this->aviso('ok', "Cuenta agregada: {$buzon->cuenta}. Pulsa «Descargar correos» para traer lo recibido.");
    }

    /** Un clic: desde la fecha elegida, todo lo que aún no se leyó de todas las cuentas, en segundo plano. */
    public function descargar(Request $request): RedirectResponse
    {
        Gate::authorize('gestionar-buzon');
        $desde = $request->validate(['desde' => ['required', 'date', 'before_or_equal:today']], attributes: ['desde' => 'fecha'])['desde'];
        $this->buzon->descargar($desde);

        return $this->aviso('info', 'Descargando los correos en segundo plano, de '.config('tramite.correo.lote').' en '.config('tramite.correo.lote').'. Van apareciendo en Expedientes.');
    }

    public function activar(Request $request): RedirectResponse
    {
        Gate::authorize('gestionar-buzon');
        $activo = $request->validate(['activo' => ['required', 'boolean']])['activo'];
        $this->buzon->activar((bool) $activo);

        return $this->aviso('ok', $activo ? 'Descarga automática encendida: se leen las cuentas cada minuto.' : 'Descarga automática apagada.');
    }

    public function principal(Buzon $buzon): RedirectResponse
    {
        Gate::authorize('gestionar-buzon');
        $this->buzon->hacerPrincipal($buzon);

        return $this->aviso('ok', "Los documentos ahora salen desde {$buzon->cuenta}.");
    }

    public function quitar(Buzon $buzon): RedirectResponse
    {
        Gate::authorize('gestionar-buzon');
        $this->buzon->quitar($buzon);

        return $this->aviso('ok', "Se quitó {$buzon->cuenta}. Lo ya descargado se conserva.");
    }

    private function aviso(string $tipo, string $mensaje): RedirectResponse
    {
        Inertia::flash('toast', ['tipo' => $tipo, 'mensaje' => $mensaje]);

        return to_route('buzon.index');
    }
}
