<?php

namespace App\Policies;

use App\Models\Area;
use App\Models\User;

/** Las áreas son configuración: las gestiona quien tenga `configuracion.gestionar` (5.1). */
class AreaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('configuracion.gestionar');
    }

    public function create(User $user): bool
    {
        return $user->can('configuracion.gestionar');
    }

    public function update(User $user, Area $area): bool
    {
        return $user->can('configuracion.gestionar');
    }
}
