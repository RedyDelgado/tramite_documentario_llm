<?php

namespace App\Correo;

use Carbon\CarbonInterface;
use Closure;

/** Acceso al buzón central; cambiar de Gmail API a IMAP no toca el resto del sistema (7.1). */
interface MailboxDriver
{
    /**
     * Mensajes desde la fecha dada que aún no se leyeron, sin borrarlos ni moverlos. `$leido` dice si un uid ya
     * entró (la base lo recuerda): se salta sin descargarlo.
     *
     * @param  (Closure(string): bool)|null  $leido
     * @return iterable<MensajeCrudo>
     */
    public function pendientes(CarbonInterface $desde, int $limite, ?Closure $leido = null): iterable;

    public function marcarProcesado(MensajeCrudo $mensaje): void;

    /**
     * Fecha y remitente de cada mensaje desde la fecha, para dimensionar sin guardar nada (7.3.3).
     *
     * @return iterable<array{fecha: CarbonInterface, remitente: string}>
     */
    public function resumen(CarbonInterface $desde): iterable;
}
