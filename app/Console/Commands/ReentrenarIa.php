<?php

namespace App\Console\Commands;

use App\Services\AuditoriaService;
use App\Services\ClasificacionService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;

#[Signature('ia:reentrenar')]
#[Description('Reentrena el clasificador con las decisiones confirmadas por personas y deja activa la nueva versión')]
class ReentrenarIa extends Command
{
    public function handle(ClasificacionService $clasificacion, AuditoriaService $auditoria): int
    {
        $ejemplos = $clasificacion->ejemplosValidados();
        $this->info('Ejemplos confirmados por personas: '.count($ejemplos).'.');

        try {
            // Embeddings de todos los ejemplos: puede tardar varios minutos en CPU.
            $meta = ClasificacionService::ia(1800)->post('/train', ['ejemplos' => $ejemplos])->throw()->json();
        } catch (RequestException $e) {
            $this->error($e->response->json('detail') ?? 'El servicio de IA no pudo entrenar.');

            return self::FAILURE;
        }

        $auditoria->registrar('ia.reentrenado', 'modelo_ia', despues: ['version' => $meta['version'], 'ejemplos' => $meta['ejemplos'], 'clases' => $meta['clases']]);
        $this->info("Versión {$meta['version']} activa. Para volver atrás: php artisan ia:modelo <versión> (o --similitud).");

        return self::SUCCESS;
    }
}
