<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocio;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as CuentaGoogle;

/** Inicio de sesión con Google: pertenecer al dominio no basta, el usuario debe estar registrado y activo (5). */
class AccesoService
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    /**
     * Inicia la sesión de la cuenta de Google si corresponde a un usuario activo; todo intento queda auditado.
     *
     * @throws ReglaDeNegocio
     */
    public function iniciarConGoogle(CuentaGoogle $cuenta): User
    {
        $email = mb_strtolower((string) $cuenta->getEmail());
        $dominio = mb_strtolower((string) config('tramite.google_dominio'));
        $raw = $cuenta->getRaw();

        // `hd` lo firma Google solo para cuentas administradas por ese Workspace; el sufijo del correo no basta.
        $delDominio = $dominio !== ''
            && ($raw['hd'] ?? null) === $dominio
            && str_ends_with($email, '@'.$dominio)
            && ($raw['email_verified'] ?? false) === true;

        $user = $delDominio ? User::whereRaw('lower(email) = ?', [$email])->first() : null;
        $motivo = match (true) {
            ! $delDominio => 'fuera_del_dominio',
            ! $user => 'no_registrado',
            ! $user->activo => 'inactivo',
            default => null,
        };

        if ($motivo) {
            $this->auditoria->registrar('sesion.fallida', 'user', $user?->id, despues: ['email' => $email, 'motivo' => $motivo]);

            throw new ReglaDeNegocio($motivo === 'fuera_del_dominio'
                ? "Ingresa con tu cuenta institucional (@{$dominio})."
                : 'Tu cuenta no tiene acceso al sistema. Pídelo al administrador.');
        }

        Auth::login($user);
        $this->auditoria->registrar('sesion.iniciada', $user, despues: ['metodo' => 'google']);

        return $user;
    }
}
