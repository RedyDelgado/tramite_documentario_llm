<?php

namespace App\Console\Commands;

use App\Services\AuditoriaService;
use App\Services\ClasificacionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

#[Signature('ia:modelo {version? : Versión a activar (p. ej. v20261005120000)} {--similitud : Volver a la similitud sin entrenar}')]
#[Description('Lista las versiones del clasificador o activa una (revertir es un comando, sección 13)')]
class ModeloIa extends Command
{
    public function handle(AuditoriaService $auditoria): int
    {
        $version = $this->argument('version');

        if (! $version && ! $this->option('similitud')) {
            $modelos = ClasificacionService::ia(10)->get('/models')->throw()->json();
            $this->table(['Versión', 'Entrenado', 'Ejemplos', 'Activa'], collect($modelos['versiones'])->map(fn ($v) => [
                $v['version'], $v['entrenado_en'], $v['ejemplos'], $v['version'] === $modelos['activa'] ? 'sí' : '',
            ]));
            $this->line('Activa: '.($modelos['activa'] ?? 'similitud (sin entrenar)'));

            return self::SUCCESS;
        }

        try {
            $activa = ClasificacionService::ia(10)->post('/models/activate', ['version' => $version])->throw()->json('activa');
        } catch (RequestException $e) {
            $this->error($e->response->status() === 404 ? "No existe la versión {$version}." : 'El servicio de IA no respondió.');

            return self::FAILURE;
        }

        $auditoria->registrar('ia.modelo_activado', 'modelo_ia', despues: ['version' => $activa]);
        $this->info('Activa: '.($activa ?? 'similitud (sin entrenar)'));

        return self::SUCCESS;
    }
}
