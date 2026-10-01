<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesSeeder::class, ReglasNoTramiteSeeder::class]);

        if (app()->isLocal()) {
            $this->call(DemoSeeder::class);
        }
    }
}
