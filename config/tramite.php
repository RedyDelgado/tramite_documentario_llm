<?php

return [

    // Superadmin inicial que crea RolesSeeder; ingresa con Google desde la fase 2.
    'superadmin' => [
        'name' => env('SUPERADMIN_NAME', 'Administrador del sistema'),
        'email' => env('SUPERADMIN_EMAIL'),
    ],

    // Dominio de Google Workspace que puede iniciar sesión; vacío deshabilita el ingreso con Google.
    'google_dominio' => env('GOOGLE_DOMINIO'),

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
        // Cuenta del buzón central; el refresh token se obtiene una vez (docs/runbook-gmail.md).
        'gmail' => [
            'client_id' => env('GMAIL_CLIENT_ID'),
            'client_secret' => env('GMAIL_CLIENT_SECRET'),
            'refresh_token' => env('GMAIL_REFRESH_TOKEN'),
            'usuario' => env('GMAIL_USUARIO', 'me'),
            'etiqueta' => env('GMAIL_ETIQUETA', 'tramite/procesado'),
        ],
    ],

    // Documentos salientes (7.3.4): `gmail` envía desde la cuenta del buzón central; `mailer` usa MAIL_MAILER (log en desarrollo).
    'salientes' => [
        'driver' => env('SALIENTES_DRIVER', 'mailer'),
        // Ritmo para no chocar con los límites de envío de Google.
        'por_minuto' => (int) env('SALIENTES_POR_MINUTO', 20),
        // Remitente y copia oculta: el buzón central, para enlazar las respuestas (7.2).
        'buzon_central' => env('SALIENTES_BUZON_CENTRAL', env('MAIL_FROM_ADDRESS')),
    ],

    // Servicio de IA local (sección 10); si está caído, el sistema sigue ingresando (principio 2).
    'ai' => [
        'url' => env('AI_SERVICE_URL', 'http://ai:8000'),
        'token' => env('AI_SERVICE_TOKEN'),
    ],

    // ClamAV (11): sin host no se analiza; con host, un archivo sin analizar no entra.
    'antivirus' => [
        'host' => env('ANTIVIRUS_HOST'),
        'puerto' => (int) env('ANTIVIRUS_PUERTO', 3310),
    ],

    // Respaldos (docs/runbook-respaldos.md). La retención por defecto espera la política del pendiente 6.
    'respaldo' => [
        'directorio' => env('RESPALDO_DIRECTORIO', storage_path('app/respaldos')),
        'modelos' => env('RESPALDO_MODELOS', '/var/www/modelos_ia'),
        'dias' => (int) env('RESPALDO_DIAS', 30),
        // 32 bytes en base64 (`openssl rand -base64 32`); sin ella, los respaldos no se cifran. Se guarda aparte, con el .env.
        'clave' => env('RESPALDO_CLAVE'),
    ],

    // Primer número de una secuencia en un año dado; sin entrada empieza en 1.
    // Por defecto 2026 continúa el registro en papel (pendiente 9).
    'secuencias_inicio' => [
        'registro' => [
            (int) env('REGISTRO_INICIO_ANIO', 2026) => (int) env('REGISTRO_INICIO_NUMERO', 38),
        ],
    ],

];
