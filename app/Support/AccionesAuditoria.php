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
        'expediente.derivado' => 'Derivó',
        'expediente.en_atencion' => 'Tomó en atención',
        'expediente.conocimiento_tomado' => 'Tomó conocimiento',
        'expediente.comentado' => 'Comentó',
        'expediente.cierre_solicitado' => 'Solicitó el cierre',
        'expediente.cierre_rechazado' => 'Rechazó el cierre',
        'expediente.cerrado' => 'Cerró el expediente',
        'expediente.respondido' => 'Quedó atendido al enviarse la respuesta',
        'original.movido' => 'Movió el original en papel',
        'constancia.impresa' => 'Imprimió la constancia de recepción',
        'etiqueta.impresa' => 'Imprimió la etiqueta QR',
        'cargo.impreso' => 'Imprimió el cargo de entrega',
        'cargo.adjuntado' => 'Adjuntó el cargo firmado',
        'documento.ocr' => 'Leyó el escaneo con OCR',
        'expediente.agrupado' => 'Agrupó en una serie',
        'ia.clasificado' => 'La IA propuso área y tipo',
        'saliente.creado' => 'Redactó un documento',
        'saliente.en_revision' => 'Envió el documento a revisión',
        'saliente.devuelto' => 'Devolvió el documento con observaciones',
        'saliente.aprobado' => 'Aprobó y numeró el documento',
        'saliente.enviado' => 'Envió el documento',
        'saliente.firmado_adjunto' => 'Adjuntó el PDF firmado',
        'saliente.respondido' => 'Llegó la respuesta al documento enviado',
        'expediente.desagrupado' => 'Quitó de la serie',
        'correo.ingresado' => 'Llegó un correo',
        'correo.descargado' => 'Descargó el correo original',
        'documento.descargado' => 'Descargó un documento',
    ];

    public static function etiqueta(string $accion): string
    {
        return self::ETIQUETAS[$accion] ?? $accion;
    }
}
