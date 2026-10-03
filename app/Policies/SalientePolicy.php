<?php

namespace App\Policies;

use App\Models\DocumentoSaliente;
use App\Models\User;

/** Documentos salientes (5, 7.3.4): redactan registro, dirección y coordinación; aprueba el rol del tipo, nunca el autor. */
class SalientePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->create($user) || $user->can('expedientes.ver_todos');
    }

    public function view(User $user, DocumentoSaliente $s): bool
    {
        return $s->creado_por === $user->id
            || $user->can('expedientes.ver_todos')
            || in_array($s->area_id, $user->areasVigentes(), true)
            || ($s->expediente && $user->can('view', $s->expediente));
    }

    public function create(User $user): bool
    {
        return $user->canAny(['expedientes.registrar', 'expedientes.derivar']) || $user->areasVigentes() !== [];
    }

    /** Solo el borrador se edita; lo aprobado es inmutable (su PDF tiene hash). */
    public function update(User $user, DocumentoSaliente $s): bool
    {
        return $s->estado === 'borrador' && ($s->creado_por === $user->id || $user->can('expedientes.registrar'));
    }

    public function aprobar(User $user, DocumentoSaliente $s): bool
    {
        return $s->estado === 'en_revision'
            && $s->creado_por !== $user->id
            && match ($s->tipoDocumento->aprueba_salida) {
                // El director, como superior, también: un área con un solo coordinador no podría aprobar lo que él redacta.
                'coordinador' => in_array($s->area_id, $user->areasVigentes(), true) || $user->hasRole('director'),
                default => $user->hasRole('director'),
            };
    }

    /** Adjuntar el PDF firmado fuera del sistema: el autor, quien aprobó o registro. */
    public function firmar(User $user, DocumentoSaliente $s): bool
    {
        return $s->estado === 'aprobado' && ($s->creado_por === $user->id || $s->aprobado_por === $user->id || $user->can('expedientes.registrar'));
    }

    /** Áreas desde las que el usuario puede emitir: todas para registro y dirección; las suyas para la coordinación. */
    public static function areasEmisoras(User $user): ?array
    {
        return $user->canAny(['expedientes.registrar', 'expedientes.derivar']) ? null : $user->areasVigentes();
    }
}
