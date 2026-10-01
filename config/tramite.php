<?php

return [

    // Superadmin inicial que crea RolesSeeder; ingresa con Google desde la fase 2.
    'superadmin' => [
        'name' => env('SUPERADMIN_NAME', 'Administrador del sistema'),
        'email' => env('SUPERADMIN_EMAIL'),
    ],

];
