<?php

namespace App\Policies;

use App\Models\User;

/** Usuarios y roles: solo con `usuarios.gestionar`; delegar la configuración no lo incluye (5.1). */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('usuarios.gestionar');
    }

    public function create(User $user): bool
    {
        return $user->can('usuarios.gestionar');
    }

    public function update(User $user, User $usuario): bool
    {
        return $user->can('usuarios.gestionar');
    }
}
