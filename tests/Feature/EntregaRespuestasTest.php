<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Enums\Semaforo;
use App\Models\Area;
use App\Models\DocumentoSaliente;
use App\Models\Envio;
use App\Models\Expediente;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Services\IngestaCorreoService;
use Database\Seeders\ReglasNoTramiteSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EntregaRespuestasTest extends TestCase
{
    use RefreshDatabase;

    private Expediente $expediente;

    private DocumentoSaliente $saliente;

    private Envio $envio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesSeeder::class, ReglasNoTramiteSeeder::class]);
        Storage::fake('originales');
        config(['tramite.correo.inicio_operacion' => '2026-01-01']);
        $this->travelTo('2026-10-06 10:00:00');
        $this->expediente = Expediente::factory()->create(['estado' => EstadoExpediente::Atendido, 'anio' => 2026, 'secuencia' => 38]);
        // Un documento enviado que exige respuesta en 5 días hábiles.
        $this->saliente = DocumentoSaliente::create([
            'expediente_id' => $this->expediente->id, 'tipo_documento_id' => TipoDocumento::create(['nombre' => 'Oficio'])->id,
            'area_id' => Area::factory()->create()->id, 'asunto' => 'Solicitud de información', 'cuerpo' => 'Texto',
            'destinatarios' => [['email' => 'mesa@muni.gob.pe', 'nombre' => null]], 'requiere_respuesta' => true, 'plazo_respuesta_dias' => 5,
            'creado_por' => User::factory()->create()->id,
        ]);
        $this->saliente->forceFill(['estado' => 'enviado', 'numero' => 'OFICIO N.º 001-2026-DGA', 'enviado_at' => now(), 'fecha_limite_respuesta' => '2026-10-13'])->save();
        $this->envio = Envio::create(['documento_saliente_id' => $this->saliente->id, 'email' => 'mesa@muni.gob.pe']);
        $this->envio->forceFill(['estado' => 'enviado', 'message_id' => 'CAF123@mail.gmail.com', 'enviado_at' => now()])->save();
    }

    private function ingresar(string $eml): Expediente
    {
        return app(IngestaCorreoService::class)->procesar(str_replace("\n", "\r\n", $eml));
    }

    public function test_la_respuesta_en_hilo_se_anexa_al_expediente_y_detiene_el_plazo(): void
    {
        $this->assertSame(Semaforo::Verde, $this->saliente->semaforo());

        $expediente = $this->ingresar(<<<'EML'
            From: Mesa de partes <mesa@muni.gob.pe>
            To: tramite@uni.edu.pe
            Subject: RE: OFICIO N.º 001-2026-DGA - Solicitud de información
            Message-ID: <respuesta-1@muni.gob.pe>
            In-Reply-To: <CAF123@mail.gmail.com>
            References: <CAF123@mail.gmail.com>
            Date: Wed, 07 Oct 2026 09:00:00 -0500
            Content-Type: text/plain; charset=utf-8

            Adjuntamos la información solicitada.
            EML);

        $this->assertTrue($expediente->is($this->expediente));
        $this->assertNotNull($this->saliente->fresh()->respondido_at);
        $this->assertNull($this->saliente->fresh()->semaforo());
        $this->assertDatabaseHas('auditoria', ['accion' => 'saliente.respondido']);
        // En la línea de tiempo se ve quién respondió y sobre qué.
        $this->actingAs(User::factory()->create()->assignRole('director'))->get("/expedientes/{$expediente->id}")
            ->assertInertia(fn ($page) => $page->where('historial', fn ($h) => collect($h)->contains('detalle', 'De mesa@muni.gob.pe: «RE: OFICIO N.º 001-2026-DGA - Solicitud de información»')));
    }

    public function test_si_el_cliente_pierde_el_hilo_el_codigo_y_el_remitente_bastan(): void
    {
        $expediente = $this->ingresar(<<<'EML'
            From: mesa@muni.gob.pe
            To: tramite@uni.edu.pe
            Subject: Respuesta [REG-2026-00038]
            Message-ID: <respuesta-2@muni.gob.pe>
            Date: Wed, 07 Oct 2026 09:00:00 -0500
            Content-Type: text/plain; charset=utf-8

            Va la información.
            EML);

        $this->assertTrue($expediente->is($this->expediente));
        $this->assertNotNull($this->saliente->fresh()->respondido_at);
    }

    public function test_un_rebote_queda_registrado_y_visible_y_no_se_anexa_al_expediente(): void
    {
        $expediente = $this->ingresar(<<<'EML'
            From: Mail Delivery Subsystem <mailer-daemon@googlemail.com>
            To: tramite@uni.edu.pe
            Subject: Delivery Status Notification (Failure)
            Message-ID: <rebote-1@mx.google.com>
            Date: Tue, 06 Oct 2026 10:05:00 -0500
            MIME-Version: 1.0
            Content-Type: multipart/report; report-type=delivery-status; boundary="LIMITE"

            --LIMITE
            Content-Type: text/plain; charset=utf-8

            No se ha encontrado la dirección mesa@muni.gob.pe.
            --LIMITE
            Content-Type: message/delivery-status

            Reporting-MTA: dns; googlemail.com

            Final-Recipient: rfc822; mesa@muni.gob.pe
            Action: failed
            Status: 5.1.1
            Diagnostic-Code: smtp; 550 5.1.1 The email account that you tried to reach does not exist.
            --LIMITE
            Content-Type: text/rfc822-headers

            From: tramite@uni.edu.pe
            To: mesa@muni.gob.pe
            Subject: [REG-2026-00038] OFICIO N.º 001-2026-DGA - Solicitud de información
            Message-ID: <CAF123@mail.gmail.com>
            --LIMITE--
            EML);

        $this->envio->refresh();
        $this->assertSame('rebotado', $this->envio->estado);
        $this->assertSame('smtp; 550 5.1.1 The email account that you tried to reach does not exist.', $this->envio->detalle);
        $this->assertFalse($expediente->is($this->expediente));
        $this->assertSame(EstadoExpediente::NoTramite, $expediente->estado);
        $this->assertNull($this->saliente->fresh()->respondido_at);

        $admin = User::factory()->create()->assignRole('director');
        $this->actingAs($admin)->get('/salientes')->assertInertia(fn ($page) => $page->where('salientes.data.0.rebotes', 1));
        $this->actingAs($admin)->get("/salientes/{$this->saliente->id}")->assertInertia(fn ($page) => $page->where('detalle.envios.0.estado', 'rebotado'));
    }

    public function test_el_documento_tiene_su_propio_semaforo_por_plazo_de_respuesta(): void
    {
        $this->assertSame(Semaforo::Verde, $this->saliente->semaforo(now()->toImmutable()->setDate(2026, 10, 7)));
        $this->assertSame(Semaforo::Amarillo, $this->saliente->semaforo(now()->toImmutable()->setDate(2026, 10, 12)));
        $this->assertSame(Semaforo::Rojo, $this->saliente->semaforo(now()->toImmutable()->setDate(2026, 10, 14)));
    }
}
