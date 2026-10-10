<?php

namespace App\Policies;

use App\Models\AreaResponsable;
use App\Models\Expediente;
use App\Models\ExpedienteArea;
use App\Models\Movimiento;
use App\Models\User;
use Illuminate\Support\Collection;

/** El superadmin no tiene estos permisos: administra, pero no ve el contenido de los trámites (5). */
class ExpedientePolicy
{
    /** La bandeja: cada quien la ve filtrada a lo suyo (visiblesPara); el superadmin no tiene ninguno de estos permisos. */
    public function viewAny(User $user): bool
    {
        return $user->canAny(['expedientes.ver_todos', 'expedientes.ver_areas', 'expedientes.ver_asignados']);
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

    /** Custodia del papel: mover el original, imprimir constancia, etiqueta y cargo (7.3.1). */
    public function custodiar(User $user, Expediente $expediente): bool
    {
        return $this->registrar($user, $expediente) || $this->derivar($user, $expediente);
    }

    /** Agrupar en series (7.3.1): quien registra o deriva. */
    public function agrupar(User $user, Expediente $expediente): bool
    {
        return $this->registrar($user, $expediente) || $this->derivar($user, $expediente);
    }

    /** Derivar y reasignar: director y administrativo (5). */
    public function derivar(User $user, Expediente $expediente): bool
    {
        return $user->can('expedientes.derivar') && $this->view($user, $expediente);
    }

    /** Atender (tomar, marcar atendido, solicitar el cierre): quien es responsable de alguna de sus áreas pendientes o la coordina. */
    public function atender(User $user, Expediente $expediente): bool
    {
        return $this->areasQueAtiende($user, $expediente)->isNotEmpty();
    }

    /**
     * Las áreas aún pendientes del expediente que este usuario atiende; AtencionService actúa solo sobre ellas.
     *
     * @return Collection<int, ExpedienteArea>
     */
    public function areasQueAtiende(User $user, Expediente $expediente): Collection
    {
        return $expediente->atenciones()->where('estado', '!=', 'atendido')->get()
            ->filter(fn (ExpedienteArea $a) => $a->responsable_id === $user->id || $this->coordina($user, $expediente, $a->area_id))
            ->values();
    }

    /** Responder (lo que deja el trámite atendido): quien lo atiende, deriva o registra; un área en copia no. */
    public function responder(User $user, Expediente $expediente): bool
    {
        return $this->atender($user, $expediente) || $this->derivar($user, $expediente) || $this->registrar($user, $expediente);
    }

    public function comentar(User $user, Expediente $expediente): bool
    {
        return $this->atender($user, $expediente) || $this->derivar($user, $expediente);
    }

    /** Aprueba el rol configurado en el tipo; nunca quien registró ni quien pidió el cierre (5: quien registra no cierra). */
    public function aprobarCierre(User $user, Expediente $expediente): bool
    {
        $solicitante = Movimiento::where('expediente_id', $expediente->id)->where('tipo', 'cierre_solicitado')->latest('id')->value('user_id');

        return $user->id !== $solicitante && $this->cerrarSinAprobacion($user, $expediente);
    }

    /**
     * Quien tiene la facultad de aprobar el cierre (el rol del tipo) y no registró el trámite: si es quien lo atiende,
     * cerrarlo no espera una segunda firma, que sería la suya (el coordinador que atiende en persona, el director en Dirección).
     */
    public function cerrarSinAprobacion(User $user, Expediente $expediente): bool
    {
        return $user->id !== $expediente->registrado_por
            && match ($expediente->tipoTramite?->aprueba_cierre) {
                'director' => $user->hasRole('director'),
                'coordinador' => $expediente->atenciones()->pluck('area_id')->contains(fn (int $area) => $this->coordina($user, $expediente, $area)),
                default => false,
            };
    }

    /**
     * Titular o suplente vigente del área misma, sea cual sea su rol (el director en Dirección); quien coordina,
     * además, las áreas que dependen de las suyas.
     */
    private function coordina(User $user, Expediente $expediente, int $area): bool
    {
        // Y que pueda ver trámites: el superadmin puede figurar como titular, pero no accede a su contenido (5).
        if (AreaResponsable::where('area_id', $area)->where('user_id', $user->id)->vigentes()->exists()) {
            return $this->view($user, $expediente);
        }

        return $user->can('expedientes.ver_areas') && in_array($area, $user->areasVigentes(), true);
    }
}
