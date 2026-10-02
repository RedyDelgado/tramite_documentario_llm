<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** Roles y permisos de la sección 5 y el superadmin inicial; idempotente. */
class RolesSeeder extends Seeder
{
    /** Rol => etiqueta visible. */
    public const ROLES = [
        'superadmin' => 'Superadmin',
        'director' => 'Director',
        'administrativo' => 'Administrativo',
        'coordinador' => 'Coordinador',
        'otros' => 'Otros',
    ];

    /** Permiso => roles que lo tienen. El superadmin no ve contenido de trámites (5). */
    public const PERMISOS = [
        // Delegable a otro rol sin dar el resto de privilegios del superadmin (5.1).
        'configuracion.gestionar' => ['superadmin'],
        // Aparte de la configuración: quien asigna roles podría darse cualquier privilegio.
        'usuarios.gestionar' => ['superadmin'],
        'expedientes.ver_todos' => ['director', 'administrativo'],
        'expedientes.ver_areas' => ['coordinador'],
        'expedientes.registrar' => ['administrativo'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (array_keys(self::ROLES) as $rol) {
            Role::findOrCreate($rol);
        }

        foreach (self::PERMISOS as $permiso => $roles) {
            Permission::findOrCreate($permiso);
            foreach ($roles as $rol) {
                Role::findByName($rol)->givePermissionTo($permiso);
            }
        }

        $email = config('tramite.superadmin.email');

        if ($email) {
            User::firstOrCreate(['email' => mb_strtolower($email)], ['name' => config('tramite.superadmin.name')])
                ->assignRole('superadmin');
        }
    }
}
