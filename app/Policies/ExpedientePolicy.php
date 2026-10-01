<?php

namespace App\Policies;

use App\Models\Expediente;
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

    /** Confirmar como trámite, marcar como no trámite o anular. */
    public function registrar(User $user, Expediente $expediente): bool
    {
        return $user->can('expedientes.registrar') && $this->view($user, $expediente);
    }
}
