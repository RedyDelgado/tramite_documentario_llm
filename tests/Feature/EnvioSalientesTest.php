<?php

namespace Tests\Feature;

use App\Correo\GmailMailboxDriver;
use App\Correo\GmailSalidaCorreo;
use App\Correo\SalidaCorreo;
use App\Enums\EstadoExpediente;
use App\Jobs\EnviarDocumento;
use App\Models\Area;
use App\Models\DocumentoSaliente;
use App\Models\Envio;
use App\Models\Expediente;
use App\Models\Movimiento;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Services\EnvioService;
use App\Services\SalienteService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class EnvioSalientesTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<Email> */
    private array $enviados = [];

    private User $director;

    private Expediente $expediente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        Storage::fake('originales');
        config(['tramite.salientes.buzon_central' => 'tramite@uni.edu.pe']);
        $this->app->instance(SalidaCorreo::class, new class($this->enviados) implements SalidaCorreo
        {
            public function __construct(private array &$enviados) {}

            public function enviar(Email $email): array
            {
                $this->enviados[] = $email;

                return ['message_id' => 'gmail-'.count($this->enviados).'@mail.gmail.com', 'proveedor_id' => null];
            }
        });
        $this->director = User::factory()->create()->assignRole('director');
        $this->expediente = Expediente::factory()->create(['estado' => EstadoExpediente::EnAtencion, 'anio' => 2026, 'secuencia' => 38]);
    }

    private function enRevision(array $datos = []): DocumentoSaliente
    {
        $s = DocumentoSaliente::create($datos + [
            'expediente_id' => $this->expediente->id, 'tipo_documento_id' => TipoDocumento::create(['nombre' => 'Oficio'])->id,
            'area_id' => Area::factory()->create(['siglas' => 'DGA'])->id, 'asunto' => 'Respuesta al requerimiento', 'cuerpo' => 'Texto',
            'destinatarios' => [['email' => 'mesa@muni.gob.pe', 'nombre' => 'Mesa de partes'], ['email' => 'alcalde@muni.gob.pe', 'nombre' => null]],
            'es_respuesta' => true, 'creado_por' => User::factory()->create()->id,
        ]);
        $s->forceFill(['estado' => 'en_revision'])->save();

        return $s;
    }

    /** Aprobado y con el documento final (el Word con su número) subido: recién sale. */
    private function aprobadoConFinal(): DocumentoSaliente
    {
        $s = app(SalienteService::class)->aprobar($this->enRevision(), $this->director);
        app(EnvioService::class)->subirFirmado($s, 'Word con OFICIO N.º 001-2026-DGA', 'Oficio final.docx');

        return $s;
    }

    public function test_aprobado_sale_solo_un_correo_por_destinatario_con_codigo_y_copia_oculta(): void
    {
        $s = $this->aprobadoConFinal();

        $this->assertCount(2, $this->enviados);
        $correo = $this->enviados[0];
        $this->assertSame('[REG-2026-00038] OFICIO N.º 001-2026-DGA - Respuesta al requerimiento', $correo->getSubject());
        $this->assertSame(['mesa@muni.gob.pe'], array_map(fn ($a) => $a->getAddress(), $correo->getTo()));
        $this->assertSame(['tramite@uni.edu.pe'], array_map(fn ($a) => $a->getAddress(), $correo->getBcc()));
        $this->assertSame('tramite@uni.edu.pe', $correo->getFrom()[0]->getAddress());
        $adjunto = $correo->getAttachments()[0];
        $this->assertSame(['Word con OFICIO N.º 001-2026-DGA', 'oficio-no-001-2026-dga.docx'], [$adjunto->getBody(), $adjunto->getFilename()]);
        $this->assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $adjunto->getMediaType().'/'.$adjunto->getMediaSubtype());
        $this->assertStringStartsWith('Texto', $correo->getTextBody());

        $s->refresh();
        $this->assertSame('enviado', $s->estado);
        $this->assertSame(['gmail-1@mail.gmail.com', 'gmail-2@mail.gmail.com'], Envio::orderBy('id')->pluck('message_id')->all());
        $this->assertDatabaseHas('auditoria', ['accion' => 'saliente.enviado']);
    }

    public function test_enviar_la_respuesta_deja_atendido_el_expediente(): void
    {
        $this->aprobadoConFinal();

        $this->expediente->refresh();
        $this->assertSame(EstadoExpediente::Atendido, $this->expediente->estado);
        $this->assertNotNull($this->expediente->atendido_at);
        $this->assertSame('Respuesta enviada: OFICIO N.º 001-2026-DGA', Movimiento::where('tipo', 'respuesta')->sole()->nota);
    }

    public function test_no_sale_hasta_que_se_sube_el_documento_final(): void
    {
        $s = app(SalienteService::class)->aprobar($this->enRevision(), $this->director);
        $this->assertCount(0, $this->enviados);
        $this->assertSame('aprobado', $s->fresh()->estado);

        $firmado = RegistroFisicoTest::pdf(['OFICIO N.º 001-2026-DGA', 'Firmado digitalmente']);
        $this->actingAs($this->director)->post("/salientes/{$s->id}/firmado", ['archivo' => UploadedFile::fake()->createWithContent('firmado.pdf', $firmado)])
            ->assertSessionHasNoErrors();

        $this->assertCount(2, $this->enviados);
        $this->assertSame(hash('sha256', $firmado), hash('sha256', $this->enviados[0]->getAttachments()[0]->getBody()));
        $this->assertSame('enviado', $s->fresh()->estado);
    }

    public function test_el_envio_va_a_ritmo_controlado_y_un_fallo_definitivo_queda_visible(): void
    {
        $this->assertInstanceOf(RateLimited::class, (new EnviarDocumento(1))->middleware()[0]);

        $s = app(SalienteService::class)->aprobar($this->enRevision(), $this->director);
        $envio = Envio::create(['documento_saliente_id' => $s->id, 'email' => 'x@y.pe']);
        (new EnviarDocumento($envio->id))->failed(new RuntimeException('Gmail rechazó el destinatario'));

        $this->assertSame(['fallido', 'Gmail rechazó el destinatario'], [$envio->fresh()->estado, $envio->fresh()->detalle]);
    }

    public function test_gmail_envia_el_mensaje_crudo_y_devuelve_el_message_id_definitivo(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            'gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response(['id' => 'abc123']),
            'gmail.googleapis.com/gmail/v1/users/me/messages/abc123*' => Http::response(['payload' => ['headers' => [['name' => 'Message-Id', 'value' => '<CAF123@mail.gmail.com>']]]]),
        ]);
        $gmail = new GmailSalidaCorreo(new GmailMailboxDriver(['client_id' => 'c', 'client_secret' => 's', 'refresh_token' => 'r', 'usuario' => 'me', 'etiqueta' => 'x']));

        $resultado = $gmail->enviar((new Email)->from('tramite@uni.edu.pe')->to('a@b.pe')->subject('[REG-2026-00038] Prueba')->text('Hola'));

        $this->assertSame(['message_id' => 'CAF123@mail.gmail.com', 'proveedor_id' => 'abc123'], $resultado);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'messages/send')
            && str_contains(base64_decode(strtr($r['raw'], '-_', '+/')), 'Subject: [REG-2026-00038] Prueba'));
    }
}
