<?php

namespace App\Correo;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Buzón central por Gmail API con OAuth2 (refresh token de la cuenta del sistema, scope gmail.modify).
 * Lo procesado se marca con una etiqueta, solo para verlo en Gmail; nunca se mueve ni se borra un correo (7.1).
 */
class GmailMailboxDriver implements MailboxDriver
{
    private const API = 'https://gmail.googleapis.com/gmail/v1/users/';

    private const TOKEN = 'https://oauth2.googleapis.com/token';

    /** @param array{client_id: ?string, client_secret: ?string, refresh_token: ?string, usuario: string, etiqueta: string} $config */
    public function __construct(private readonly array $config)
    {
        if (! $config['client_id'] || ! $config['client_secret'] || ! $config['refresh_token']) {
            throw new RuntimeException('Faltan GMAIL_CLIENT_ID, GMAIL_CLIENT_SECRET o GMAIL_REFRESH_TOKEN (ver docs/runbook-gmail.md).');
        }
    }

    public function pendientes(CarbonInterface $desde, int $limite, ?Closure $leido = null): iterable
    {
        // Lo ya leído lo sabe la base, no la etiqueta: se lista todo desde la fecha (solo ids, barato) y se salta lo conocido.
        $entregados = 0;
        foreach ($this->ids('after:'.$desde->format('Y/m/d').' -in:chats', PHP_INT_MAX) as $id) {
            if ($leido && $leido($id)) {
                continue;
            }
            $raw = $this->api()->get("messages/{$id}", ['format' => 'raw'])->throw()->json('raw');
            yield new MensajeCrudo($id, $this->base64url($raw));
            // Se corta al completar el lote, sin pedir la página siguiente.
            if (++$entregados >= $limite) {
                return;
            }
        }
    }

    public function marcarProcesado(MensajeCrudo $mensaje): void
    {
        $this->api()->post("messages/{$mensaje->uid}/modify", ['addLabelIds' => [$this->idEtiqueta()]])->throw();
    }

    public function resumen(CarbonInterface $desde): iterable
    {
        foreach ($this->ids('after:'.$desde->format('Y/m/d').' -in:chats', PHP_INT_MAX) as $id) {
            $mensaje = $this->api()->get("messages/{$id}", ['format' => 'metadata', 'metadataHeaders' => 'From'])->throw()->json();
            $de = collect($mensaje['payload']['headers'] ?? [])->firstWhere('name', 'From')['value'] ?? '';

            yield [
                'fecha' => CarbonImmutable::createFromTimestampMs((int) $mensaje['internalDate']),
                'remitente' => mb_strtolower(preg_match('/<([^>]+)>/', $de, $m) ? $m[1] : trim($de)),
            ];
        }
    }

    /** @return iterable<string> */
    private function ids(string $consulta, int $limite): iterable
    {
        $entregados = 0;
        $pagina = null;

        do {
            $respuesta = $this->api()->get('messages', array_filter([
                'q' => $consulta,
                'maxResults' => min(500, $limite - $entregados),
                'pageToken' => $pagina,
            ]))->throw()->json();

            foreach ($respuesta['messages'] ?? [] as $mensaje) {
                yield $mensaje['id'];
                if (++$entregados >= $limite) {
                    return;
                }
            }
            $pagina = $respuesta['nextPageToken'] ?? null;
        } while ($pagina);
    }

    /** Envía un mensaje RFC 822 completo; devuelve el id de Gmail. */
    public function enviarCrudo(string $mensaje): string
    {
        return $this->api()->post('messages/send', ['raw' => rtrim(strtr(base64_encode($mensaje), '+/', '-_'), '=')])->throw()->json('id');
    }

    public function messageId(string $id): ?string
    {
        $cabeceras = $this->api()->get("messages/{$id}", ['format' => 'metadata', 'metadataHeaders' => 'Message-ID'])->throw()->json('payload.headers', []);
        $valor = collect($cabeceras)->first(fn ($c) => strcasecmp($c['name'], 'Message-ID') === 0)['value'] ?? null;

        return $valor ? trim($valor, '<> ') : null;
    }

    private function idEtiqueta(): string
    {
        $nombre = $this->config['etiqueta'];

        return Cache::rememberForever("gmail.etiqueta.{$nombre}", function () use ($nombre) {
            $existente = collect($this->api()->get('labels')->throw()->json('labels', []))->firstWhere('name', $nombre);

            return $existente['id'] ?? $this->api()->post('labels', [
                'name' => $nombre,
                'labelListVisibility' => 'labelShow',
                'messageListVisibility' => 'show',
            ])->throw()->json('id');
        });
    }

    private function api(): PendingRequest
    {
        return Http::withToken($this->token())
            ->baseUrl(self::API.rawurlencode($this->config['usuario']).'/')
            ->acceptJson()
            ->timeout(30)
            // Cuota de Google: ante 429 o 5xx se reintenta con espera creciente.
            ->retry(3, fn (int $intento) => $intento * 1000, fn ($e) => $e instanceof RequestException && in_array($e->response->status(), [429, 500, 502, 503], true));
    }

    private function token(): string
    {
        $clave = 'gmail.token.'.md5($this->config['client_id'].$this->config['refresh_token']);

        if ($token = Cache::get($clave)) {
            return $token;
        }

        $respuesta = Http::asForm()->timeout(30)->post(self::TOKEN, [
            'client_id' => $this->config['client_id'],
            'client_secret' => $this->config['client_secret'],
            'refresh_token' => $this->config['refresh_token'],
            'grant_type' => 'refresh_token',
        ])->throw()->json();

        // Se guarda en caché un minuto menos de lo que dura, para no usar uno vencido.
        Cache::put($clave, $respuesta['access_token'], max(60, ((int) ($respuesta['expires_in'] ?? 3600)) - 60));

        return $respuesta['access_token'];
    }

    private function base64url(string $valor): string
    {
        return base64_decode(strtr($valor, '-_', '+/'), true) ?: throw new RuntimeException('Gmail devolvió un mensaje en un formato inesperado.');
    }
}
