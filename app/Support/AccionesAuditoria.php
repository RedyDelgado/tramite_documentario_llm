<?php

namespace App\Support;

/** Textos legibles de las acciones de auditoría para la línea de tiempo. */
final class AccionesAuditoria
{
    private const ETIQUETAS = [
        'expediente.creado' => 'Ingresó al sistema',
        'expediente.consultado' => 'Consultó el expediente',
        'expediente.no_tramite' => 'Marcó como no trámite',
        'expediente.devuelto_a_revision' => 'Devolvió a revisión',
        'registro.asignado' => 'Registró como trámite',
        'registro.anulado' => 'Anuló el registro',
        'correo.ingresado' => 'Llegó un correo',
        'correo.descargado' => 'Descargó el correo original',
        'documento.descargado' => 'Descargó un documento',
    ];

    public static function etiqueta(string $accion): string
    {
        return self::ETIQUETAS[$accion] ?? $accion;
    }
}
