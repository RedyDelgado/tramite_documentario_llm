<?php

namespace App\Console\Commands;

use App\Services\RespaldoService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('respaldo:restaurar {carpeta : Carpeta del respaldo, p. ej. storage/app/respaldos/2026-10-02_023000} {--force : No pedir confirmación}')]
#[Description('Reemplaza la base, los originales y los modelos de IA con un respaldo verificado')]
class RestaurarRespaldo extends Command
{
    public function handle(RespaldoService $respaldos): int
    {
        $carpeta = rtrim($this->argument('carpeta'), '/');
        $base = config('database.connections.'.config('database.default').'.database');

        if (! $this->option('force') && ! $this->confirm("Se reemplazarán la base «{$base}» y los archivos con el respaldo ".basename($carpeta).'; lo registrado después de ese respaldo se pierde. ¿Continuar?')) {
            return self::FAILURE;
        }

        try {
            $respaldos->restaurar($carpeta);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Restaurado. Reinicia el servicio de IA para que cargue el modelo activo: docker compose restart ai');

        return self::SUCCESS;
    }
}
