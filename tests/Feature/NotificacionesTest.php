<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Mail\ResumenDiario;
use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\Expediente;
use App\Models\NotificacionEnviada;
use App\Models\User;
use App\Services\IngestaCorreoService;
use Database\Seeders\ReglasNoTramiteSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class NotificacionesTest extends TestCase
{
    use RefreshDatabase;

    private User $coordinador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesSeeder::class, ReglasNoTramiteSeeder::class]);
        Storage::fake('originales');
        $this->travelTo('2026-10-20 07:30:00');
        config(['mail.from.address' => 'tramite@uni.edu.pe', 'tramite.correo.inicio_operacion' => '2026-10-01']);
        $area = Area::factory()->create();
        $this->coordinador = User::factory()->create(['email' => 'coordinador@uni.edu.pe'])->assignRole('coordinador');
        AreaResponsable::create(['area_id' => $area->id, 'user_id' => $this->coordinador->id, 'tipo' => 'titular', 'vigente_desde' => '2026-01-01']);
        Expediente::factory()->create(['estado' => EstadoExpediente::Derivado, 'area_principal_id' => $area->id, 'fecha_limite' => '2026-10-10']);
    }

    public function test_el_resumen_enviado_queda_registrado_con_su_message_id(): void
    {
        // Cola síncrona y transporte en memoria (phpunit.xml): el correo sale de verdad por el mailer.
        $this->artisan('resumen:diario')->assertSuccessful();

        $notificacion = NotificacionEnviada::sole();
        $this->assertSame(['enviado', 'coordinador@uni.edu.pe', 'resumen_diario'], [$notificacion->estado, $notificacion->email, $notificacion->tipo]);
        $this->assertNotNull($notificacion->enviado_at);
        $enviado = app('mailer')->getSymfonyTransport()->messages()->sole();
        $this->assertSame("<{$notificacion->message_id}>", $enviado->getOriginalMessage()->getHeaders()->get('Message-ID')->getBodyAsString());
        $this->assertDatabaseHas('auditoria', ['accion' => 'notificacion.resumen_diario']);
    }

    public function test_si_la_cola_agota_los_intentos_queda_fallido_con_el_motivo(): void
    {
        $notificacion = NotificacionEnviada::create([
            'user_id' => $this->coordinador->id, 'email' => $this->coordinador->email, 'tipo' => 'resumen_diario',
            'message_id' => NotificacionEnviada::nuevoMessageId('resumen'),
        ]);

        (new ResumenDiario($this->coordinador, collect(), $notificacion))->failed(new RuntimeException('Gmail no respondió (503)'));

        $this->assertSame(['fallido', 'Gmail no respondió (503)'], [$notificacion->fresh()->estado, $notificacion->fresh()->detalle]);
    }

    public function test_un_rebote_que_cita_el_message_id_lo_marca_rebotado(): void
    {
        $this->artisan('resumen:diario')->assertSuccessful();
        $notificacion = NotificacionEnviada::sole();

        $expediente = app(IngestaCorreoService::class)->procesar(str_replace("\n", "\r\n", <<<EML
            From: Mail Delivery Subsystem <mailer-daemon@googlemail.com>
            To: tramite@uni.edu.pe
            Subject: Delivery Status Notification (Failure)
            Message-ID: <rebote-9@mx.google.com>
            Date: Tue, 20 Oct 2026 07:31:00 -0500
            MIME-Version: 1.0
            Content-Type: multipart/report; report-type=delivery-status; boundary="LIMITE"

            --LIMITE
            Content-Type: text/plain; charset=utf-8

            No se ha encontrado la dirección coordinador@uni.edu.pe.
            --LIMITE
            Content-Type: message/delivery-status

            Final-Recipient: rfc822; coordinador@uni.edu.pe
            Action: failed
            Diagnostic-Code: smtp; 550 5.1.1 The email account that you tried to reach does not exist.
            --LIMITE
            Content-Type: text/rfc822-headers

            From: tramite@uni.edu.pe
            To: coordinador@uni.edu.pe
            Subject: Trámites pendientes: 1 (1 en rojo)
            Message-ID: <{$notificacion->message_id}>
            --LIMITE--
            EML));

        $notificacion->refresh();
        $this->assertSame('rebotado', $notificacion->estado);
        $this->assertSame('smtp; 550 5.1.1 The email account that you tried to reach does not exist.', $notificacion->detalle);
        $this->assertSame(EstadoExpediente::NoTramite, $expediente->estado);
        $this->assertDatabaseHas('auditoria', ['accion' => 'notificacion.rebotada']);
    }

    public function test_solo_quien_administra_la_configuracion_ve_la_lista(): void
    {
        $this->artisan('resumen:diario')->assertSuccessful();

        $this->actingAs(User::factory()->create()->assignRole('superadmin'))->get('/notificaciones')->assertOk()
            ->assertInertia(fn ($page) => $page->component('notificaciones/Index')
                ->where('notificaciones.data.0.email', 'coordinador@uni.edu.pe')
                ->where('notificaciones.data.0.estado', 'enviado')
                ->where('notificaciones.data.0.expedientes', 1)
                ->where('notificaciones.meta.total', 1));
        $this->actingAs(User::factory()->create()->assignRole('director'))->get('/notificaciones')->assertForbidden();
        $this->actingAs($this->coordinador)->get('/notificaciones')->assertForbidden();
    }
}
