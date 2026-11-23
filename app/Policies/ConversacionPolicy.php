<?php

namespace App\Policies;

use App\Models\Conversacion;
use App\Models\User;

/** Mensajes: solo quien participa lee y escribe; ningún rol, ni el superadmin, ve conversaciones ajenas. */
class ConversacionPolicy
{
    public function create(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user, Conversacion $conversacion): bool
    {
        return $conversacion->tieneA($user);
    }

    public function enviar(User $user, Conversacion $conversacion): bool
    {
        return $user->activo && $conversacion->tieneA($user);
    }
}
