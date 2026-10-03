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
 * Respaldo y restauración (docs/runbook-respaldos.md). Cada respaldo es una carpeta con la base (pg_dump), los modelos
 * de IA (tar.gz) y el manifiesto de originales (SHA-256 y ruta); los originales van a un almacén común (`archivos/`),
 * una vez por contenido, así que un respaldo diario no los vuelve a copiar. Con RESPALDO_CLAVE todo se cifra
 * (libsodium secretstream: autenticado y por trozos). El SHA256SUMS de cada carpeta se comprueba sin la clave.
 * Meilisearch y Redis no se respaldan: el índice se reconstruye desde la base y lo pendiente se vuelve a encolar.
 */
class RespaldoService
{
    private const FORMATO = 'Y-m-d_His';

    private const CIFRADO = '.cifrado';

    private const TROZO = 1 << 20;

    public function __construct(private AuditoriaService $auditoria, private ColaService $colas, private RolBaseDatosService $roles) {}

    /** Devuelve la carpeta creada; si algo falla, la borra para no dejar un respaldo a medias. */
    public function crear(): string
    {
        $raiz = config('tramite.respaldo.directorio');
        $carpeta = $raiz.'/'.now()->format(self::FORMATO);
        $clave = $this->clave();
        File::ensureDirectoryExists($carpeta);

        try {
            $this->postgres('pg_dump', ['--format=custom', '--file='.$carpeta.'/base.dump.tmp']);
            $this->guardar($carpeta.'/base.dump.tmp', $carpeta.'/base.dump', $clave);
            if (is_dir(config('tramite.respaldo.modelos'))) {
                Process::timeout(3600)->run(['tar', '-czf', $carpeta.'/modelos.tar.gz.tmp', '-C', config('tramite.respaldo.modelos'), '.'])->throw();
                $this->guardar($carpeta.'/modelos.tar.gz.tmp', $carpeta.'/modelos.tar.gz', $clave);
            }
            File::put($carpeta.'/originales.txt', $this->copiarOriginales($raiz.'/archivos', $clave));
            // Mismo formato que sha256sum, para poder comprobarlo a mano en otra máquina y sin la clave.
            File::put($carpeta.'/SHA256SUMS', collect(File::files($carpeta))
                ->map(fn ($archivo) => hash_file('sha256', $archivo->getPathname()).'  '.$archivo->getFilename()."\n")
                ->implode(''));
        } catch (Throwable $e) {
            File::deleteDirectory($carpeta);
            throw $e;
        }

        $this->auditoria->registrar('respaldo.creado', 'respaldo', basename($carpeta), despues: ['cifrado' => $clave !== null]);

        return $carpeta;
    }

    /** Borra los respaldos más antiguos que la retención y los originales del almacén que ya ninguno usa. */
    public function podar(): int
    {
        $raiz = config('tramite.respaldo.directorio');
        $limite = now()->subDays(config('tramite.respaldo.dias'));
        [$vencidos, $vigentes] = $this->carpetas($raiz)
            ->partition(fn ($carpeta) => Carbon::createFromFormat(self::FORMATO, basename($carpeta))->lt($limite));
        $vencidos->each(fn ($carpeta) => File::deleteDirectory($carpeta));

        $usados = $vigentes->flatMap(fn ($carpeta) => array_column($this->manifiesto($carpeta), 0))->flip();
        if (is_dir($raiz.'/archivos')) {
            collect(File::allFiles($raiz.'/archivos'))
                ->reject(fn ($archivo) => $usados->has(basename($archivo->getFilename(), self::CIFRADO)))
                ->each(fn ($archivo) => File::delete($archivo->getPathname()));
        }

        return $vencidos->count();
    }

    /** Reemplaza la base y los archivos; un respaldo dañado, incompleto o con otra clave se rechaza antes de tocar nada. */
    public function restaurar(string $carpeta): void
    {
        $clave = $this->clave();
        $this->verificar($carpeta);
        $almacen = dirname($carpeta).'/archivos';
        $originales = $this->manifiesto($carpeta);
        // ponytail: cada original se descifra dos veces (comprobar y escribir); restaurar es raro y el volumen, chico.
        foreach ($originales as [$sha, $ruta]) {
            $this->leerOriginal($almacen, $sha, $clave);
        }

        $temporales = [];
        try {
            $dump = $this->abrir($carpeta, 'base.dump', $clave, $temporales);
            $modelos = $this->abrir($carpeta, 'modelos.tar.gz', $clave, $temporales);

            // En una transacción: si falla, la base queda como estaba.
            // Sin los permisos del respaldo: en un servidor nuevo el rol de la aplicación aún no existe; se aplican después.
            $this->postgres('pg_restore', ['--clean', '--if-exists', '--no-owner', '--no-privileges', '--single-transaction', $dump]);
            $this->roles->aplicar();

            $disco = Storage::disk('originales');
            foreach ($originales as [$sha, $ruta]) {
                if (! $disco->exists($ruta) || hash_file('sha256', $disco->path($ruta)) !== $sha) {
                    $disco->put($ruta, $this->leerOriginal($almacen, $sha, $clave));
                }
            }
            if ($modelos) {
                File::ensureDirectoryExists(config('tramite.respaldo.modelos'));
                Process::timeout(3600)->run(['tar', '-xzf', $modelos, '-C', config('tramite.respaldo.modelos')])->throw();
            }
        } finally {
            array_map(File::delete(...), $temporales);
        }

        // El índice puede tener expedientes posteriores al respaldo (o no existir, en un servidor nuevo): se rehace desde la base.
        Artisan::call('scout:sync-index-settings');
        Artisan::call('scout:flush', ['model' => Expediente::class]);
        Artisan::call('scout:import', ['model' => Expediente::class]);
        // En un servidor nuevo la cola está vacía: lo pendiente según la base vuelve a encolarse.
        $this->colas->reencolar();

        $this->auditoria->registrar('respaldo.restaurado', 'respaldo', basename($carpeta));
    }

    /** Clave de 32 bytes en base64 (`openssl rand -base64 32`); sin ella, los respaldos no se cifran. */
    private function clave(): ?string
    {
        $texto = (string) config('tramite.respaldo.clave');
        if ($texto === '') {
            return null;
        }
        $clave = base64_decode($texto, true);
        if ($clave === false || strlen($clave) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
            throw new RuntimeException('RESPALDO_CLAVE debe ser de 32 bytes en base64: genérala con `openssl rand -base64 32`.');
        }

        return $clave;
    }

    /** @return \Illuminate\Support\Collection<int, string> carpetas de respaldo (no el almacén ni copias con otro nombre) */
    private function carpetas(string $raiz)
    {
        return collect(is_dir($raiz) ? File::directories($raiz) : [])
            ->filter(fn ($carpeta) => preg_match('/^\d{4}-\d{2}-\d{2}_\d{6}$/', basename($carpeta)))
            ->values();
    }

    /** @return list<array{0: string, 1: string}> SHA-256 y ruta de cada original */
    private function manifiesto(string $carpeta): array
    {
        $lineas = is_file($carpeta.'/originales.txt') ? file($carpeta.'/originales.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

        return array_map(fn ($linea) => explode('  ', $linea, 2), $lineas);
    }

    /** Copia al almacén los originales que aún no tiene y devuelve el manifiesto (formato sha256sum). */
    private function copiarOriginales(string $almacen, ?string $clave): string
    {
        $disco = Storage::disk('originales');

        return collect($disco->allFiles())->map(function (string $ruta) use ($disco, $almacen, $clave) {
            $sha = hash_file('sha256', $disco->path($ruta));
            $destino = $this->enAlmacen($almacen, $sha).($clave ? self::CIFRADO : '');
            if (! is_file($destino)) {
                File::ensureDirectoryExists(dirname($destino));
                $this->guardar($disco->path($ruta), $destino, $clave, copiar: true);
            }

            return "{$sha}  {$ruta}\n";
        })->implode('');
    }

    private function enAlmacen(string $almacen, string $sha): string
    {
        return $almacen.'/'.substr($sha, 0, 2).'/'.$sha;
    }

    /** Contenido de un original del almacén, comprobado contra su SHA-256. */
    private function leerOriginal(string $almacen, string $sha, ?string $clave): string
    {
        $ruta = $this->enAlmacen($almacen, $sha);
        if (is_file($ruta.self::CIFRADO)) {
            $salida = fopen('php://temp', 'w+b');
            $this->descifrar($ruta.self::CIFRADO, $salida, $this->exigirClave($clave));
            rewind($salida);
            $contenido = stream_get_contents($salida);
            fclose($salida);
        } elseif (is_file($ruta)) {
            $contenido = File::get($ruta);
        } else {
            throw new RuntimeException("Falta en el almacén el original {$sha}: el respaldo está incompleto.");
        }

        if (! hash_equals($sha, hash('sha256', $contenido))) {
            throw new RuntimeException("El original {$sha} no coincide con su suma: el almacén está dañado.");
        }

        return $contenido;
    }

    /**
     * Ruta legible de un archivo del respaldo: descifrado a un temporal si hace falta (y anotado para borrarlo).
     *
     * @param  list<string>  $temporales
     */
    private function abrir(string $carpeta, string $nombre, ?string $clave, array &$temporales): ?string
    {
        if (is_file("{$carpeta}/{$nombre}".self::CIFRADO)) {
            $temporales[] = $temporal = tempnam(sys_get_temp_dir(), 'respaldo-');
            $salida = fopen($temporal, 'wb');
            try {
                $this->descifrar("{$carpeta}/{$nombre}".self::CIFRADO, $salida, $this->exigirClave($clave));
            } finally {
                fclose($salida);
            }

            return $temporal;
        }

        return is_file("{$carpeta}/{$nombre}") ? "{$carpeta}/{$nombre}" : null;
    }

    private function exigirClave(?string $clave): string
    {
        return $clave ?? throw new RuntimeException('Este respaldo está cifrado y falta RESPALDO_CLAVE en el .env.');
    }

    /** Mueve (o copia) un archivo al respaldo; con clave, lo deja cifrado con el sufijo .cifrado. */
    private function guardar(string $origen, string $destino, ?string $clave, bool $copiar = false): void
    {
        if ($clave === null) {
            $copiar ? File::copy($origen, $destino) : File::move($origen, $destino);

            return;
        }

        $destino = str_ends_with($destino, self::CIFRADO) ? $destino : $destino.self::CIFRADO;
        $entrada = fopen($origen, 'rb');
        $salida = fopen($destino, 'wb');
        try {
            [$estado, $cabecera] = sodium_crypto_secretstream_xchacha20poly1305_init_push($clave);
            fwrite($salida, $cabecera);
            // El último trozo lleva la marca FINAL: un archivo recortado no pasa al descifrar.
            $trozo = fread($entrada, self::TROZO);
            do {
                $siguiente = feof($entrada) ? '' : fread($entrada, self::TROZO);
                $final = $siguiente === '' && feof($entrada);
                $cifrado = sodium_crypto_secretstream_xchacha20poly1305_push($estado, (string) $trozo, '',
                    $final ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE);
                fwrite($salida, pack('N', strlen($cifrado)).$cifrado);
                $trozo = $siguiente;
            } while (! $final);
        } finally {
            fclose($entrada);
            fclose($salida);
        }
        if (! $copiar) {
            File::delete($origen);
        }
    }

    /** @param  resource  $salida */
    private function descifrar(string $origen, $salida, string $clave): void
    {
        $entrada = fopen($origen, 'rb');
        try {
            $cabecera = fread($entrada, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
            $estado = sodium_crypto_secretstream_xchacha20poly1305_init_pull((string) $cabecera, $clave);
            do {
                $largo = fread($entrada, 4);
                $resultado = strlen((string) $largo) === 4
                    ? sodium_crypto_secretstream_xchacha20poly1305_pull($estado, (string) fread($entrada, unpack('N', $largo)[1]))
                    : false;
                if ($resultado === false) {
                    throw new RuntimeException(basename($origen).' no se pudo descifrar: la clave no es la del respaldo o el archivo está dañado.');
                }
                fwrite($salida, $resultado[0]);
            } while ($resultado[1] !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
        } finally {
            fclose($entrada);
        }
    }

    private function verificar(string $carpeta): void
    {
        $sumas = is_file($carpeta.'/SHA256SUMS') ? file($carpeta.'/SHA256SUMS', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
        $archivos = array_map(fn ($linea) => explode('  ', $linea, 2), $sumas);
        $nombres = array_column($archivos, 1);

        if (! array_intersect(['base.dump', 'base.dump'.self::CIFRADO], $nombres) || ! in_array('originales.txt', $nombres, true)) {
            throw new RuntimeException("{$carpeta} no es un respaldo completo (falta SHA256SUMS, la base o el manifiesto de originales).");
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
        // El dueño: el rol de la aplicación no puede recrear tablas.
        $db = config('database.connections.pgsql_dueno');

        Process::env(['PGPASSWORD' => $db['password']])->timeout(3600)->run([
            $programa,
            '--host='.$db['host'],
            '--port='.$db['port'],
            '--username='.$db['username'],
            '--dbname='.$db['database'],
            ...$argumentos,
        ])->throw();
    }
}
