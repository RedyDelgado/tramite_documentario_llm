<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/** Datos de ejemplo mínimos, solo en local; el catálogo real se carga desde el panel. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $direccion = Area::firstOrCreate(['nombre' => 'Dirección'], [
            'descripcion' => 'Dirección de la filial.',
            'palabras_clave' => ['dirección', 'resolución', 'convenio'],
            'orden' => 1,
        ]);

        $escuela = Area::firstOrCreate(['nombre' => 'Escuela Profesional de ejemplo'], [
            'descripcion' => 'Coordinación académica de la escuela.',
            'palabras_clave' => ['docentes', 'sílabo', 'horario'],
            'orden' => 2,
        ]);

        Area::firstOrCreate(['nombre' => 'Laboratorio de cómputo'], [
            'descripcion' => 'Área dependiente de la escuela.',
            'palabras_clave' => ['equipos', 'software', 'laboratorio'],
            'parent_id' => $escuela->id,
            'orden' => 1,
        ]);

        Area::firstOrCreate(['nombre' => 'Mesa de partes'], [
            'descripcion' => 'Recepción y registro de documentos.',
            'palabras_clave' => ['oficio', 'solicitud', 'carta'],
            'parent_id' => $direccion->id,
            'orden' => 1,
        ]);

        // Un usuario por rol para el acceso de desarrollo; el coordinador es titular de la escuela.
        foreach (['director' => 'Directora de prueba', 'administrativo' => 'Administrativo de prueba', 'coordinador' => 'Coordinador de prueba'] as $rol => $nombre) {
            $usuario = User::firstOrCreate(['email' => "{$rol}@demo.example"], ['name' => $nombre]);
            $usuario->syncRoles([$rol]);
        }
        AreaResponsable::firstOrCreate(
            ['area_id' => $escuela->id, 'user_id' => User::where('email', 'coordinador@demo.example')->value('id')],
            ['tipo' => 'titular', 'vigente_desde' => today()->startOfYear()],
        );

        // Buzón de prueba con los correos ficticios de los tests: `php artisan correo:importar`.
        File::copyDirectory(base_path('tests/fixtures/correos'), config('tramite.correo.directorio'));
    }
}
