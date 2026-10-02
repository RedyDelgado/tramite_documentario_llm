<?php

namespace App\Policies;

use App\Models\CorreccionPendiente;
use App\Models\User;

/** Panel de la IA (10): lo ven quienes validan correcciones o administran la configuración. */
class IaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny(['ia.validar', 'configuracion.gestionar']);
    }

    /** Nunca valida quien hizo la corrección: una sola persona no puede entrenar a la IA a su gusto. */
    public function resolver(User $user, CorreccionPendiente $correccion): bool
    {
        return $user->can('ia.validar') && $correccion->usuario_id !== $user->id;
    }
}
