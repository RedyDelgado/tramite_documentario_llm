<?php

return [

    // Superadmin inicial que crea RolesSeeder; ingresa con Google desde la fase 2.
    'superadmin' => [
        'name' => env('SUPERADMIN_NAME', 'Administrador del sistema'),
        'email' => env('SUPERADMIN_EMAIL'),
    ],

    'registro' => [
        // Formato visible (6.2); el código de enlace siempre es REG-AAAA-NNNNN.
        'formato' => env('REGISTRO_FORMATO', 'N°%05d'),
    ],

    'correo' => [
        // El job programado solo corre con el buzón configurado y autorizado (pendiente 1).
        'activo' => (bool) env('CORREO_ACTIVO', false),
        'driver' => env('CORREO_DRIVER', 'directorio'),
        'directorio' => env('CORREO_DIRECTORIO', storage_path('app/buzon-prueba')),
        // Histórico mínimo: no se lee nada anterior a esta fecha (7.3.3).
        'backfill_desde' => env('CORREO_BACKFILL_DESDE', '2026-01-01'),
        // Lo recibido antes de la puesta en marcha entra como histórico: sin semáforo ni avisos.
        'inicio_operacion' => env('CORREO_INICIO_OPERACION'),
        // Mensajes por ejecución del job (ritmo limitado para la cuota de Google).
        'lote' => (int) env('CORREO_LOTE', 50),
    ],

    // Primer número de una secuencia en un año dado; sin entrada empieza en 1.
    // Por defecto 2026 continúa el registro en papel (pendiente 9).
    'secuencias_inicio' => [
        'registro' => [
            (int) env('REGISTRO_INICIO_ANIO', 2026) => (int) env('REGISTRO_INICIO_NUMERO', 38),
        ],
    ],

];
