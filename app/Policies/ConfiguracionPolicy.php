<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Catálogos de configuración (5.1): los gestiona quien tenga `configuracion.gestionar`, delegable. */
class ConfiguracionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('configuracion.gestionar');
    }

    public function create(User $user): bool
    {
        return $user->can('configuracion.gestionar');
    }

    public function update(User $user, Model $modelo): bool
    {
        return $user->can('configuracion.gestionar');
    }

    public function delete(User $user, Model $modelo): bool
    {
        return $user->can('configuracion.gestionar');
    }
}
