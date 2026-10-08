<?php

namespace App\Http\Controllers;

use App\Correo\MailboxDriver;
use App\Jobs\IngestarCorreos;
use App\Services\BuzonService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as RedireccionExterna;

/** Buzón central (7.1) desde el panel: conectar la cuenta de Google, descargar ahora y la descarga automática. Solo el superadmin. */
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

    /** A Google, con el permiso de Gmail y acceso permanente (refresh token): se elige la cuenta del buzón central. */
    public function conectar(): RedireccionExterna
    {
        Gate::authorize('gestionar-buzon');

        return Socialite::driver('google')
            ->redirectUrl($this->buzon->redireccion())
            ->scopes([self::PERMISO_GMAIL])
            ->with(array_filter(['access_type' => 'offline', 'prompt' => 'consent select_account', 'login_hint' => config('tramite.salientes.buzon_central')]))
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

        $this->buzon->conectar((string) $cuenta->getEmail(), $cuenta->refreshToken);

        return $this->aviso('ok', "Buzón conectado: {$cuenta->getEmail()}. Ya puedes descargar los correos.");
    }

    /** Lee un lote ahora, en la cola; si ya hay una lectura en curso, no se duplica (IngestarCorreos es único). */
    public function descargar(): RedirectResponse
    {
        Gate::authorize('gestionar-buzon');
        IngestarCorreos::dispatch();

        return $this->aviso('info', 'Descargando correos. En unos segundos aparecen en Expedientes, como «Por revisar».');
    }

    /** Vuelve a leer desde una fecha lo que el buzón ya tenía marcado como procesado, y empieza a descargarlo. */
    public function reabrir(Request $request, MailboxDriver $buzon): RedirectResponse
    {
        Gate::authorize('gestionar-buzon');
        $desde = $request->validate(['desde' => ['required', 'date', 'before_or_equal:today']], attributes: ['desde' => 'fecha'])['desde'];
        $cantidad = $this->buzon->reabrir($buzon, CarbonImmutable::parse($desde));
        IngestarCorreos::dispatch();

        return $this->aviso('ok', $cantidad
            ? "Se volverán a descargar {$cantidad} correos, de a ".config('tramite.correo.lote').' por minuto. Lo ya registrado no se duplica.'
            : 'No había correos procesados desde esa fecha.');
    }

    public function activar(Request $request): RedirectResponse
    {
        Gate::authorize('gestionar-buzon');
        $activo = $request->validate(['activo' => ['required', 'boolean']])['activo'];
        $this->buzon->activar((bool) $activo);

        return $this->aviso('ok', $activo ? 'Descarga automática encendida: se lee el buzón cada minuto.' : 'Descarga automática apagada.');
    }

    public function desconectar(): RedirectResponse
    {
        Gate::authorize('gestionar-buzon');
        $this->buzon->desconectar();

        return $this->aviso('ok', 'Buzón desconectado. Lo ya descargado se conserva.');
    }

    private function aviso(string $tipo, string $mensaje): RedirectResponse
    {
        Inertia::flash('toast', ['tipo' => $tipo, 'mensaje' => $mensaje]);

        return to_route('buzon.index');
    }
}
