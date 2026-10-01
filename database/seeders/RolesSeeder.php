<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Roles de la sección 5 y el superadmin inicial; idempotente. */
class RolesSeeder extends Seeder
{
    public const ROLES = ['superadmin', 'director', 'administrativo', 'coordinador', 'otros'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::ROLES as $rol) {
            Role::findOrCreate($rol);
        }

        // Delegable a otro rol sin dar el resto de privilegios del superadmin (5.1).
        Permission::findOrCreate('configuracion.gestionar');
        Role::findByName('superadmin')->givePermissionTo('configuracion.gestionar');

        $email = config('tramite.superadmin.email');

        if ($email) {
            User::firstOrCreate(['email' => $email], ['name' => config('tramite.superadmin.name')])
                ->assignRole('superadmin');
        }
    }
}
