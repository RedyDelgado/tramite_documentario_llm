<?php

namespace Database\Seeders;

use App\Models\Area;
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

        // Buzón de prueba con los correos ficticios de los tests: `php artisan correo:importar`.
        File::copyDirectory(base_path('tests/fixtures/correos'), config('tramite.correo.directorio'));
    }
}
