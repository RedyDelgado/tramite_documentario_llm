<?php

namespace App\Policies;

use App\Models\User;

/** Como el resto de la configuración, pero quien registra expedientes también da de alta emisores en línea (7.3.5). */
class EmisorPolicy extends ConfiguracionPolicy
{
    public function create(User $user): bool
    {
        return $user->can('configuracion.gestionar') || $user->can('expedientes.registrar');
    }
}
