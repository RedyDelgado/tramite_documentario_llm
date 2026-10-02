<?php

namespace App\Services;

use App\Models\Expediente;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Respaldo y restauración (docs/runbook-respaldos.md): la base con pg_dump, los originales y los
 * modelos de IA en tar.gz, y un SHA256SUMS que se verifica antes de restaurar. Meilisearch y Redis
 * no se respaldan: el índice se reconstruye desde la base y la cola se vacía al restaurar.
 */
class RespaldoService
{
    private const FORMATO = 'Y-m-d_His';

    public function __construct(private AuditoriaService $auditoria) {}

    /** Devuelve la carpeta creada; si algo falla, la borra para no dejar un respaldo a medias. */
    public function crear(): string
    {
        $carpeta = config('tramite.respaldo.directorio').'/'.now()->format(self::FORMATO);
        File::ensureDirectoryExists($carpeta);

        try {
            $this->postgres('pg_dump', ['--format=custom', '--file='.$carpeta.'/base.dump']);
            $this->empaquetar(Storage::disk('originales')->path(''), $carpeta.'/originales.tar.gz');
            if (is_dir(config('tramite.respaldo.modelos'))) {
                $this->empaquetar(config('tramite.respaldo.modelos'), $carpeta.'/modelos.tar.gz');
            }
            // Mismo formato que sha256sum, para poder comprobarlo a mano en otra máquina.
            File::put($carpeta.'/SHA256SUMS', collect(File::files($carpeta))
                ->map(fn ($archivo) => hash_file('sha256', $archivo->getPathname()).'  '.$archivo->getFilename()."\n")
                ->implode(''));
        } catch (Throwable $e) {
            File::deleteDirectory($carpeta);
            throw $e;
        }

        $this->auditoria->registrar('respaldo.creado', 'respaldo', basename($carpeta));

        return $carpeta;
    }

    /** Borra los respaldos más antiguos que la retención; no toca carpetas con otro nombre. */
    public function podar(): int
    {
        $limite = now()->subDays(config('tramite.respaldo.dias'));

        return collect(File::directories(config('tramite.respaldo.directorio')))
            ->filter(fn ($carpeta) => preg_match('/^\d{4}-\d{2}-\d{2}_\d{6}$/', basename($carpeta))
                && Carbon::createFromFormat(self::FORMATO, basename($carpeta))->lt($limite))
            ->each(fn ($carpeta) => File::deleteDirectory($carpeta))
            ->count();
    }

    /** Reemplaza la base y los archivos; un respaldo dañado se rechaza antes de tocar nada. */
    public function restaurar(string $carpeta): void
    {
        $this->verificar($carpeta);

        // En una transacción: si falla, la base queda como estaba y los archivos no se tocan.
        $this->postgres('pg_restore', ['--clean', '--if-exists', '--no-owner', '--single-transaction', $carpeta.'/base.dump']);
        $this->desempaquetar($carpeta.'/originales.tar.gz', Storage::disk('originales')->path(''));
        if (is_file($carpeta.'/modelos.tar.gz')) {
            $this->desempaquetar($carpeta.'/modelos.tar.gz', config('tramite.respaldo.modelos'));
        }

        // El índice puede tener expedientes posteriores al respaldo: se rehace desde la base.
        Artisan::call('scout:flush', ['model' => Expediente::class]);
        Artisan::call('scout:import', ['model' => Expediente::class]);

        $this->auditoria->registrar('respaldo.restaurado', 'respaldo', basename($carpeta));
    }

    private function verificar(string $carpeta): void
    {
        $sumas = is_file($carpeta.'/SHA256SUMS') ? file($carpeta.'/SHA256SUMS', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        $archivos = array_map(fn ($linea) => explode('  ', $linea, 2), $sumas);

        if (! in_array('base.dump', array_column($archivos, 1), true) || ! in_array('originales.tar.gz', array_column($archivos, 1), true)) {
            throw new RuntimeException("{$carpeta} no es un respaldo completo (falta SHA256SUMS, base.dump u originales.tar.gz).");
        }

        foreach ($archivos as [$hash, $archivo]) {
            if (! is_file("{$carpeta}/{$archivo}") || hash_file('sha256', "{$carpeta}/{$archivo}") !== $hash) {
                throw new RuntimeException("{$archivo} no coincide con su suma de verificación: el respaldo está dañado.");
            }
        }
    }

    /** @param  list<string>  $argumentos */
    private function postgres(string $programa, array $argumentos): void
    {
        $db = config('database.connections.'.config('database.default'));

        Process::env(['PGPASSWORD' => $db['password']])->timeout(3600)->run([
            $programa,
            '--host='.$db['host'],
            '--port='.$db['port'],
            '--username='.$db['username'],
            '--dbname='.$db['database'],
            ...$argumentos,
        ])->throw();
    }

    private function empaquetar(string $directorio, string $archivo): void
    {
        File::ensureDirectoryExists($directorio);
        Process::timeout(3600)->run(['tar', '-czf', $archivo, '-C', $directorio, '.'])->throw();
    }

    private function desempaquetar(string $archivo, string $directorio): void
    {
        File::ensureDirectoryExists($directorio);
        Process::timeout(3600)->run(['tar', '-xzf', $archivo, '-C', $directorio])->throw();
    }
}
