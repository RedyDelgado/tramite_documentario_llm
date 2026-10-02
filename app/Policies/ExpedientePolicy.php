<?php

namespace App\Policies;

use App\Models\Expediente;
use App\Models\Movimiento;
use App\Models\User;

/** El superadmin no tiene estos permisos: administra, pero no ve el contenido de los trámites (5). */
class ExpedientePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['expedientes.ver_todos', 'expedientes.ver_areas']);
    }

    public function view(User $user, Expediente $expediente): bool
    {
        return Expediente::whereKey($expediente->id)->visiblesPara($user)->exists();
    }

    /** Registrar un documento en papel (7.3). */
    public function create(User $user): bool
    {
        return $user->can('expedientes.registrar');
    }

    /** Confirmar como trámite, marcar como no trámite o anular. */
    public function registrar(User $user, Expediente $expediente): bool
    {
        return $user->can('expedientes.registrar') && $this->view($user, $expediente);
    }

    /** Derivar y reasignar: director y administrativo (5). */
    public function derivar(User $user, Expediente $expediente): bool
    {
        return $user->can('expedientes.derivar') && $this->view($user, $expediente);
    }

    /** Atender (tomar, solicitar el cierre): el responsable asignado o quien coordina el área. */
    public function atender(User $user, Expediente $expediente): bool
    {
        return $expediente->responsable_id === $user->id || $this->coordinaElArea($user, $expediente);
    }

    public function comentar(User $user, Expediente $expediente): bool
    {
        return $this->atender($user, $expediente) || $this->derivar($user, $expediente);
    }

    /** Aprueba el rol configurado en el tipo; nunca quien registró ni quien pidió el cierre (5: quien registra no cierra). */
    public function aprobarCierre(User $user, Expediente $expediente): bool
    {
        $rol = $expediente->tipoTramite?->aprueba_cierre;
        $solicitante = Movimiento::where('expediente_id', $expediente->id)->where('tipo', 'cierre_solicitado')->latest('id')->value('user_id');

        return $rol !== null
            && $user->id !== $expediente->registrado_por
            && $user->id !== $solicitante
            && match ($rol) {
                'director' => $user->hasRole('director'),
                'coordinador' => $this->coordinaElArea($user, $expediente),
                default => false,
            };
    }

    private function coordinaElArea(User $user, Expediente $expediente): bool
    {
        return $expediente->area_principal_id !== null
            && $user->can('expedientes.ver_areas')
            && in_array($expediente->area_principal_id, $user->areasVigentes(), true);
    }
}
