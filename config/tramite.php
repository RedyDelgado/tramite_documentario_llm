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

    // Primer número de una secuencia en un año dado; sin entrada empieza en 1.
    // Por defecto 2026 continúa el registro en papel (pendiente 9).
    'secuencias_inicio' => [
        'registro' => [
            (int) env('REGISTRO_INICIO_ANIO', 2026) => (int) env('REGISTRO_INICIO_NUMERO', 38),
        ],
    ],

];
