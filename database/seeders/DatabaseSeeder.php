<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Estructura real de la filial: también en producción (los correos se completan en Usuarios).
        $this->call([RolesSeeder::class, ReglasNoTramiteSeeder::class, FilialSeeder::class]);
    }
}
