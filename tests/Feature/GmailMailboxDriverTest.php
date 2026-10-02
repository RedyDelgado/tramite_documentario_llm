<?php

namespace Tests\Feature;

use App\Correo\GmailMailboxDriver;
use App\Correo\MailboxDriver;
use App\Correo\MensajeCrudo;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/** Contra respuestas simuladas de Google; con el buzón de prueba autorizado se valida en vivo. */
class GmailMailboxDriverTest extends TestCase
{
    private const CONFIG = [
        'client_id' => 'cliente', 'client_secret' => 'secreto', 'refresh_token' => 'refresco',
        'usuario' => 'me', 'etiqueta' => 'tramite/procesado',
    ];

    /** @var list<string> */
    private array $etiquetas = [];

    private function base64url(string $valor): string
    {
        return rtrim(strtr(base64_encode($valor), '+/', '-_'), '=');
    }

    private function simularGoogle(): void
    {
        $eml = File::get(base_path('tests/fixtures/correos/oficio-con-pdf.eml'));

        Http::fake(function (Request $r) use ($eml) {
            $url = $r->url();

            return match (true) {
                str_starts_with($url, 'https://oauth2.googleapis.com/token') => Http::response(['access_token' => 'acceso', 'expires_in' => 3599]),
                str_contains($url, '/messages?') && ! str_contains($url, 'pageToken') => Http::response(['messages' => [['id' => 'm1'], ['id' => 'm2']], 'nextPageToken' => 'p2']),
                str_contains($url, '/messages?') => Http::response(['messages' => [['id' => 'm3']]]),
                str_contains($url, 'format=raw') => Http::response(['id' => 'x', 'raw' => $this->base64url($eml)]),
                str_contains($url, 'format=metadata') => Http::response([
                    'internalDate' => '1791036900000',
                    'payload' => ['headers' => [['name' => 'From', 'value' => 'Municipalidad <MesaDePartes@munidemo.example>']]],
                ]),
                str_ends_with($url, '/labels') && $r->method() === 'GET' => Http::response(['labels' => array_map(fn ($n) => ['id' => 'L1', 'name' => $n], $this->etiquetas)]),
                str_ends_with($url, '/labels') => tap(Http::response(['id' => 'L1']), fn () => $this->etiquetas[] = $r['name']),
                str_ends_with($url, '/modify') => Http::response(['id' => 'm1']),
                default => Http::response('no simulado: '.$url, 500),
            };
        });
    }

    public function test_trae_los_pendientes_en_crudo_excluyendo_lo_ya_etiquetado(): void
    {
        $this->simularGoogle();
        $driver = new GmailMailboxDriver(self::CONFIG);

        $mensajes = iterator_to_array($driver->pendientes(CarbonImmutable::parse('2026-01-01'), 50), false);

        $this->assertSame(['m1', 'm2', 'm3'], array_map(fn (MensajeCrudo $m) => $m->uid, $mensajes));
        $this->assertSame(File::get(base_path('tests/fixtures/correos/oficio-con-pdf.eml')), $mensajes[0]->contenido);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/messages?')
            && $r['q'] === 'after:2026/01/01 -label:tramite-procesado -in:chats'
            && $r->hasHeader('Authorization', 'Bearer acceso'));
        // Un solo token para todas las llamadas.
        Http::assertSentCount(1 + 2 + 3);
        $this->assertCount(1, Http::recorded(fn (Request $r) => str_contains($r->url(), 'oauth2.googleapis.com')));
    }

    public function test_respeta_el_limite_del_lote(): void
    {
        $this->simularGoogle();

        $mensajes = iterator_to_array((new GmailMailboxDriver(self::CONFIG))->pendientes(CarbonImmutable::parse('2026-01-01'), 2), false);

        $this->assertCount(2, $mensajes);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'pageToken'));
    }

    public function test_marca_con_la_etiqueta_y_la_crea_una_sola_vez(): void
    {
        $this->simularGoogle();
        $driver = new GmailMailboxDriver(self::CONFIG);

        $driver->marcarProcesado(new MensajeCrudo('m1', ''));
        $driver->marcarProcesado(new MensajeCrudo('m2', ''));

        $this->assertSame(['tramite/procesado'], $this->etiquetas);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'messages/m2/modify') && $r['addLabelIds'] === ['L1']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'delete') || str_contains($r->url(), 'trash'));
    }

    public function test_el_resumen_solo_lee_metadatos(): void
    {
        $this->simularGoogle();

        $resumen = iterator_to_array((new GmailMailboxDriver(self::CONFIG))->resumen(CarbonImmutable::parse('2026-01-01')), false);

        $this->assertCount(3, $resumen);
        $this->assertSame('mesadepartes@munidemo.example', $resumen[0]['remitente']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'format=raw'));
    }

    public function test_sin_credenciales_falla_con_un_mensaje_claro(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GMAIL_REFRESH_TOKEN');

        new GmailMailboxDriver([...self::CONFIG, 'refresh_token' => null]);
    }

    public function test_la_configuracion_elige_el_driver(): void
    {
        config(['tramite.correo.driver' => 'gmail', 'tramite.correo.gmail' => self::CONFIG]);

        $this->assertInstanceOf(GmailMailboxDriver::class, app(MailboxDriver::class));
    }
}
