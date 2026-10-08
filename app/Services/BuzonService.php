<?php

namespace App\Services;

use App\Correo\GmailMailboxDriver;
use App\Correo\MailboxDriver;
use App\Jobs\IngestarCorreos;
use App\Models\Buzon;
use App\Models\Configuracion;
use App\Models\CorreoLeido;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * El buzón central (7.1): una o varias cuentas de Google conectadas desde el panel (la principal envía) o, sin ninguna,
 * el buzón del .env (carpeta de prueba en desarrollo). Guarda desde qué fecha se descarga, si la descarga automática
 * está encendida y el resultado de cada lectura.
 */
class BuzonService
{
    private const ACTIVO = 'correo.activo';

    private const DESDE = 'correo.desde';

    private const LECTURA = 'correo.ultima_lectura';

    // Mientras dura una descarga en segundo plano, para mostrarlo en Buzón central.
    public const EN_CURSO = 'correo.descarga_en_curso';

    public function __construct(private readonly AuditoriaService $auditoria) {}

    public function conectado(): bool
    {
        return Buzon::exists();
    }

    /** `gmail` si hay cuentas conectadas desde el panel; si no, lo que diga el .env (`directorio` en desarrollo). */
    public function driver(): string
    {
        return $this->conectado() ? 'gmail' : config('tramite.correo.driver');
    }

    public function principal(): ?Buzon
    {
        return Buzon::orderByDesc('principal')->orderBy('id')->first();
    }

    /**
     * Credenciales de Gmail de una cuenta (la principal si no se indica): el cliente OAuth es el del inicio de sesión.
     *
     * @return array{client_id: ?string, client_secret: ?string, refresh_token: ?string, usuario: string, etiqueta: string}
     */
    public function gmail(?Buzon $buzon = null): array
    {
        $env = config('tramite.correo.gmail');
        $buzon ??= $this->principal();
        if (! $buzon) {
            return $env;
        }

        return [
            'client_id' => $env['client_id'] ?: config('services.google.client_id'),
            'client_secret' => $env['client_secret'] ?: config('services.google.client_secret'),
            'refresh_token' => $buzon->refresh_token,
            'usuario' => 'me',
            'etiqueta' => $env['etiqueta'],
        ];
    }

    /**
     * Los buzones que se leen: cada cuenta conectada o, sin ninguna, el del .env.
     *
     * @return list<array{0: string, 1: MailboxDriver, 2: ?Buzon}> [nombre, lector, cuenta]
     */
    public function lectores(): array
    {
        $buzones = Buzon::orderByDesc('principal')->orderBy('id')->get();
        if ($buzones->isEmpty()) {
            return [[config('tramite.correo.driver'), app(MailboxDriver::class), null]];
        }

        return $buzones->map(fn (Buzon $b) => [$b->cuenta, new GmailMailboxDriver($this->gmail($b)), $b])->all();
    }

    /** Descarga automática cada minuto: lo que se eligió en el panel o, si no, CORREO_ACTIVO. */
    public function activo(): bool
    {
        return (bool) (Configuracion::find(self::ACTIVO)?->valor ?? config('tramite.correo.activo'));
    }

    /** Desde qué fecha se descarga: la elegida en el panel o, si no, CORREO_BACKFILL_DESDE. */
    public function desde(): CarbonImmutable
    {
        return CarbonImmutable::parse(Configuracion::find(self::DESDE)?->valor ?? config('tramite.correo.backfill_desde'))->startOfDay();
    }

    /** Un clic: fija la fecha y descarga en segundo plano todo lo recibido desde entonces que aún no se leyó. */
    public function descargar(string $desde): void
    {
        $antes = $this->desde()->toDateString();
        Configuracion::updateOrCreate(['clave' => self::DESDE], ['valor' => $desde]);
        if ($antes !== $desde) {
            $this->auditoria->registrar('buzon.desde', 'buzon', antes: ['desde' => $antes], despues: ['desde' => $desde]);
        }
        Cache::put(self::EN_CURSO, true, 120);
        IngestarCorreos::dispatch();
    }

    /** Agrega una cuenta (o renueva su acceso); la primera queda como principal. */
    public function conectar(string $cuenta, string $refreshToken): Buzon
    {
        return DB::transaction(function () use ($cuenta, $refreshToken) {
            $buzon = Buzon::firstOrNew(['cuenta' => mb_strtolower($cuenta)]);
            $buzon->fill(['refresh_token' => $refreshToken, 'principal' => $buzon->principal || ! Buzon::where('principal', true)->exists()])->save();
            // Nunca el token en la auditoría: solo qué cuenta quedó conectada.
            $this->auditoria->registrar('buzon.conectado', 'buzon', $buzon->id, despues: ['cuenta' => $buzon->cuenta, 'principal' => $buzon->principal]);

            return $buzon;
        });
    }

    public function quitar(Buzon $buzon): void
    {
        DB::transaction(function () use ($buzon) {
            $buzon->delete();
            // Si era la principal, la siguiente cuenta pasa a enviar.
            if ($buzon->principal) {
                Buzon::orderBy('id')->first()?->forceFill(['principal' => true])->save();
            }
            $this->auditoria->registrar('buzon.desconectado', 'buzon', $buzon->id, antes: ['cuenta' => $buzon->cuenta]);
        });
    }

    public function hacerPrincipal(Buzon $buzon): void
    {
        DB::transaction(function () use ($buzon) {
            $anterior = $this->principal();
            Buzon::where('principal', true)->update(['principal' => false]);
            $buzon->forceFill(['principal' => true])->save();
            $this->auditoria->registrar('buzon.principal', 'buzon', $buzon->id, antes: ['cuenta' => $anterior?->cuenta], despues: ['cuenta' => $buzon->cuenta]);
        });
    }

    public function activar(bool $activo): void
    {
        $antes = $this->activo();
        Configuracion::updateOrCreate(['clave' => self::ACTIVO], ['valor' => $activo]);
        $this->auditoria->registrar('buzon.descarga_automatica', 'buzon', antes: ['activa' => $antes], despues: ['activa' => $activo]);
    }

    /** @param array{procesados: int, fallidos: int} $resultado */
    public function registrarLectura(array $resultado, ?string $error = null, ?Buzon $cuenta = null): void
    {
        $lectura = ['fecha' => now()->toIso8601String(), ...$resultado, 'error' => $error ? Str::limit($error, 300) : null];
        $cuenta
            ? $cuenta->forceFill(['ultima_lectura' => $lectura])->save()
            : Configuracion::updateOrCreate(['clave' => self::LECTURA], ['valor' => $lectura]);
    }

    /** @return array<string, mixed> lo que muestra la pantalla Buzón central; nunca un token */
    public function estado(): array
    {
        $leidos = CorreoLeido::selectRaw('buzon, count(*) as total')->groupBy('buzon')->pluck('total', 'buzon');

        return [
            'cuentas' => Buzon::orderByDesc('principal')->orderBy('id')->get()->map(fn (Buzon $b) => [
                'id' => $b->id,
                'cuenta' => $b->cuenta,
                'principal' => $b->principal,
                'descargados' => (int) ($leidos[$b->cuenta] ?? 0),
                'ultima_lectura' => $b->ultima_lectura,
            ])->all(),
            'driver' => $this->driver(),
            'activo' => $this->activo(),
            'desde' => $this->desde()->toDateString(),
            'en_curso' => Cache::has(self::EN_CURSO),
            // Sin cuentas conectadas: lo leído del buzón del .env (carpeta de prueba).
            'prueba' => $this->conectado() ? null : ['descargados' => (int) ($leidos[config('tramite.correo.driver')] ?? 0), 'ultima_lectura' => Configuracion::find(self::LECTURA)?->valor],
            'inicio_operacion' => config('tramite.correo.inicio_operacion'),
            'cliente_configurado' => filled(config('tramite.correo.gmail.client_id') ?: config('services.google.client_id')),
            'redireccion' => $this->redireccion(),
        ];
    }

    /** Fija desde APP_URL y no desde la petición: así es la misma URI que se registra en Google (127.0.0.1 y localhost son distintas). */
    public function redireccion(): string
    {
        return rtrim(config('app.url'), '/').'/buzon/google/callback';
    }
}
