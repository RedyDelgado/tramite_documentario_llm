<?php

namespace App\Correo;

use Carbon\CarbonInterface;

/** Acceso al buzón central; cambiar de Gmail API a IMAP no toca el resto del sistema (7.1). */
interface MailboxDriver
{
    /**
     * Mensajes aún no marcados como procesados, desde la fecha dada, sin borrarlos ni moverlos.
     *
     * @return iterable<MensajeCrudo>
     */
    public function pendientes(CarbonInterface $desde, int $limite): iterable;

    public function marcarProcesado(MensajeCrudo $mensaje): void;

    /**
     * Fecha y remitente de cada mensaje desde la fecha, para dimensionar sin guardar nada (7.3.3).
     *
     * @return iterable<array{fecha: CarbonInterface, remitente: string}>
     */
    public function resumen(CarbonInterface $desde): iterable;
}
