<?php

namespace App\Services;

use App\Correo\MailboxDriver;
use App\Models\Configuracion;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * El buzón central (7.1): se conecta desde el panel con la cuenta de Google del buzón (el refresh token queda cifrado
 * con APP_KEY en la base) o, si no se conectó ahí, por el .env (docs/runbook-gmail.md). También dice si la descarga
 * automática está encendida y guarda el resultado de la última lectura para mostrarlo.
 */
class BuzonService
{
    private const TOKEN = 'correo.gmail.refresh_token';

    private const CUENTA = 'correo.gmail.cuenta';

    private const ACTIVO = 'correo.activo';

    private const LECTURA = 'correo.ultima_lectura';

    public function __construct(private readonly AuditoriaService $auditoria) {}

    /** Fija desde APP_URL y no desde la petición: así es la misma URI que se registra en Google (127.0.0.1 y localhost son distintas). */
    public function redireccion(): string
    {
        return rtrim(config('app.url'), '/').'/buzon/google/callback';
    }

    public function conectado(): bool
    {
        return Configuracion::whereKey(self::TOKEN)->exists();
    }

    /** `gmail` si se conectó desde el panel; si no, lo que diga el .env (`directorio` en desarrollo). */
    public function driver(): string
    {
        return $this->conectado() ? 'gmail' : config('tramite.correo.driver');
    }

    /**
     * Credenciales de Gmail: el cliente OAuth es el del inicio de sesión (o GMAIL_CLIENT_*), el token el del panel.
     *
     * @return array{client_id: ?string, client_secret: ?string, refresh_token: ?string, usuario: string, etiqueta: string}
     */
    public function gmail(): array
    {
        $env = config('tramite.correo.gmail');
        $token = Configuracion::find(self::TOKEN)?->valor;
        if (! $token) {
            return $env;
        }

        return [
            'client_id' => $env['client_id'] ?: config('services.google.client_id'),
            'client_secret' => $env['client_secret'] ?: config('services.google.client_secret'),
            'refresh_token' => Crypt::decryptString($token),
            'usuario' => 'me',
            'etiqueta' => $env['etiqueta'],
        ];
    }

    /** Descarga automática cada minuto: lo que se eligió en el panel o, si no, CORREO_ACTIVO. */
    public function activo(): bool
    {
        return (bool) (Configuracion::find(self::ACTIVO)?->valor ?? config('tramite.correo.activo'));
    }

    public function conectar(string $cuenta, string $refreshToken): void
    {
        Configuracion::updateOrCreate(['clave' => self::TOKEN], ['valor' => Crypt::encryptString($refreshToken)]);
        Configuracion::updateOrCreate(['clave' => self::CUENTA], ['valor' => $cuenta]);
        // Nunca el token en la auditoría: solo qué cuenta quedó conectada.
        $this->auditoria->registrar('buzon.conectado', 'buzon', despues: ['cuenta' => $cuenta]);
    }

    public function desconectar(): void
    {
        $cuenta = Configuracion::find(self::CUENTA)?->valor;
        Configuracion::whereKey([self::TOKEN, self::CUENTA])->delete();
        $this->auditoria->registrar('buzon.desconectado', 'buzon', antes: ['cuenta' => $cuenta]);
    }

    public function activar(bool $activo): void
    {
        $antes = $this->activo();
        Configuracion::updateOrCreate(['clave' => self::ACTIVO], ['valor' => $activo]);
        $this->auditoria->registrar('buzon.descarga_automatica', 'buzon', antes: ['activa' => $antes], despues: ['activa' => $activo]);
    }

    /**
     * Vuelve a leer lo recibido desde la fecha (p. ej. tras vaciar la base): quita la etiqueta de procesado en Gmail.
     * No duplica: cada correo se reconoce por su Message-ID y su hash al ingresar (7.3.5).
     */
    public function reabrir(MailboxDriver $buzon, CarbonInterface $desde): int
    {
        $cantidad = $buzon->reabrir($desde);
        $this->auditoria->registrar('buzon.reabierto', 'buzon', despues: ['desde' => $desde->toDateString(), 'correos' => $cantidad]);

        return $cantidad;
    }

    /** @param array{procesados: int, fallidos: int} $resultado */
    public function registrarLectura(array $resultado, ?string $error = null): void
    {
        Configuracion::updateOrCreate(['clave' => self::LECTURA], ['valor' => [
            'fecha' => now()->toIso8601String(), ...$resultado, 'error' => $error ? Str::limit($error, 300) : null,
        ]]);
    }

    /** @return array<string, mixed> lo que muestra la pantalla Buzón central; nunca el token */
    public function estado(): array
    {
        return [
            'conectado' => $this->conectado(),
            'cuenta' => Configuracion::find(self::CUENTA)?->valor,
            'driver' => $this->driver(),
            'activo' => $this->activo(),
            'ultima_lectura' => Configuracion::find(self::LECTURA)?->valor,
            'desde' => config('tramite.correo.backfill_desde'),
            'inicio_operacion' => config('tramite.correo.inicio_operacion'),
            'cliente_configurado' => filled(config('tramite.correo.gmail.client_id') ?: config('services.google.client_id')),
            'redireccion' => $this->redireccion(),
        ];
    }
}
