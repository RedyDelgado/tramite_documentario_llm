<?php

namespace App\Services;

use App\Enums\EstadoExpediente;
use App\Enums\Semaforo;
use App\Models\AreaResponsable;
use App\Models\Configuracion;
use App\Models\Expediente;
use Carbon\CarbonImmutable;

/** Semáforo de la sección 8: reglas deterministas, nunca IA; umbrales desde `configuraciones`. */
class SemaforoService
{
    /** Calcula el semáforo según plazo restante, responsable y días sin movimiento; null si el estado no lleva semáforo. */
    public function calcular(Expediente $e, ?CarbonImmutable $hoy = null): ?Semaforo
    {
        $hoy ??= CarbonImmutable::today();

        return match ($e->estado) {
            EstadoExpediente::Historico, EstadoExpediente::NoTramite, EstadoExpediente::Anulado,
            EstadoExpediente::Atendido, EstadoExpediente::Cerrado => null,
            // Aún sin tipo ni destino: pendiente de clasificar.
            EstadoExpediente::PorRevisar, EstadoExpediente::Registrado => Semaforo::Gris,
            default => $this->enAtencion($e, $hoy),
        };
    }

    private function enAtencion(Expediente $e, CarbonImmutable $hoy): Semaforo
    {
        $limite = $e->fecha_limite ? CarbonImmutable::parse($e->fecha_limite->toDateString()) : null;

        // «Para conocimiento» sin plazo nunca pasa a rojo (8).
        $puedeVencer = $e->requiere_respuesta || $limite;
        if (($puedeVencer && ! $this->tieneResponsable($e)) || ($limite && $hoy->gt($limite))) {
            return Semaforo::Rojo;
        }

        if ($limite) {
            $inicio = $this->dia($e->fecha_ingreso);
            $total = max(1, $inicio->diffInDays($limite));
            $restante = $hoy->diffInDays($limite);
            if ($restante / $total * 100 < Configuracion::valor('semaforo.porcentaje_amarillo')) {
                return Semaforo::Amarillo;
            }
        }

        $ultimo = $e->ultimo_movimiento_at ?? $e->registrado_at ?? $e->fecha_ingreso;
        $quieto = $this->dia($ultimo)->diffInDays($hoy);

        return $quieto > Configuracion::valor('semaforo.dias_sin_movimiento') ? Semaforo::Amarillo : Semaforo::Verde;
    }

    /** Día calendario en Lima de un instante. */
    private function dia(\DateTimeInterface $instante): CarbonImmutable
    {
        return CarbonImmutable::instance($instante)->setTimezone(config('app.timezone'))->startOfDay();
    }

    /** Cada área pendiente tiene responsable directo, o un titular o suplente vigente: basta una sin nadie para el rojo. */
    private function tieneResponsable(Expediente $e): bool
    {
        $pendientes = $e->atenciones()->where('estado', '!=', 'atendido')->get(['area_id', 'responsable_id']);

        return $pendientes->isNotEmpty() && $pendientes->every(fn ($a) => $a->responsable_id !== null
            || AreaResponsable::where('area_id', $a->area_id)->vigentes()->exists());
    }

    /** Recalcula lo abierto, que cambia de color con el paso del tiempo; devuelve cuántos cambiaron. */
    public function recalcularAbiertos(): int
    {
        $cambiados = 0;
        Expediente::whereIn('estado', [EstadoExpediente::Derivado, EstadoExpediente::EnAtencion])
            ->lazyById(200)
            ->each(function (Expediente $e) use (&$cambiados) {
                if ($this->calcular($e) !== $e->semaforo) {
                    // save() recalcula (Expediente::booted) y actualiza el índice de búsqueda.
                    $e->save();
                    $cambiados++;
                }
            });

        return $cambiados;
    }
}
