<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Jobs\EvaluarRespuesta;
use App\Mail\AvisoDerivacion;
use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\Buzon;
use App\Models\Correo;
use App\Models\Expediente;
use App\Models\Movimiento;
use App\Models\TipoTramite;
use App\Models\User;
use App\Services\AtencionService;
use App\Services\IngestaCorreoService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** La derivación sale del buzón con el código; la respuesta del área vuelve al buzón, la IA propone y una persona confirma. */
class RespuestaPorCorreoTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private User $coordinador;

    private Expediente $expediente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        Storage::fake('originales');
        $this->travelTo('2026-10-05 09:00:00');
        Buzon::create(['cuenta' => 'tramite@uni.edu.pe', 'refresh_token' => 'x', 'principal' => true]);
        $this->area = Area::factory()->create(['nombre' => 'Servicios Académicos']);
        $this->coordinador = User::factory()->create(['email' => 'servicios@uni.edu.pe'])->assignRole('coordinador');
        AreaResponsable::create(['area_id' => $this->area->id, 'user_id' => $this->coordinador->id, 'tipo' => 'titular', 'vigente_desde' => '2026-01-01']);
        $this->expediente = Expediente::factory()->create([
            'estado' => EstadoExpediente::Registrado, 'anio' => 2026, 'secuencia' => 38, 'asunto' => 'Lista de matriculados 2026-II', 'fecha_ingreso' => now(),
        ]);
        $this->actingAs(User::factory()->create()->assignRole('administrativo'));
        app(AtencionService::class)->derivar($this->expediente, [
            'tipo_tramite_id' => TipoTramite::create(['nombre' => 'Requerimiento', 'plazo_dias' => 5])->id,
            'areas' => [['area_id' => $this->area->id]], 'requiere_respuesta' => true, 'nota' => 'Remitir la lista de matriculados.',
        ]);
    }

    private function respuesta(string $de = 'servicios@uni.edu.pe'): Correo
    {
        $eml = implode("\r\n", [
            "From: Servicios <{$de}>",
            'To: tramite@uni.edu.pe',
            'Subject: RE: [REG-2026-00038] Para atender: Lista de matriculados 2026-II',
            'Message-ID: <respuesta-38@uni.edu.pe>',
            'Date: Mon, 05 Oct 2026 10:00:00 -0500',
            'Content-Type: text/plain; charset=utf-8',
            '',
            'Adjunto la lista de matriculados solicitada.',
        ]);

        return app(IngestaCorreoService::class)->procesar($eml, 'respuesta-38')->correos()->sole();
    }

    public function test_el_aviso_sale_del_buzon_principal_con_el_codigo_en_el_asunto(): void
    {
        Mail::fake();
        $otra = Area::factory()->create();
        $coordinadorOtra = User::factory()->create()->assignRole('coordinador');
        AreaResponsable::create(['area_id' => $otra->id, 'user_id' => $coordinadorOtra->id, 'tipo' => 'titular', 'vigente_desde' => '2026-01-01']);

        app(AtencionService::class)->derivar($this->expediente->fresh(), [
            'tipo_tramite_id' => $this->expediente->fresh()->tipo_tramite_id, 'requiere_respuesta' => true,
            'areas' => [['area_id' => $this->area->id], ['area_id' => $otra->id]],
        ]);

        // Sale de la cuenta principal del buzón: la respuesta vuelve ahí y el código la enlaza al trámite.
        Mail::assertQueued(AvisoDerivacion::class, fn (AvisoDerivacion $m) => $m->hasTo($coordinadorOtra->email)
            && $m->envelope()->from->address === 'tramite@uni.edu.pe'
            && str_starts_with($m->envelope()->subject, '[REG-2026-00038] Para atender:'));
    }

    public function test_la_respuesta_del_area_se_anexa_y_la_ia_propone_si_responde(): void
    {
        Queue::fake();
        $correo = $this->respuesta();

        $this->assertSame($this->expediente->id, $correo->expediente_id);
        $this->assertTrue($correo->respuesta_area);
        Queue::assertPushed(EvaluarRespuesta::class, fn ($job) => $job->correoId === $correo->id);
        // Nada se da por atendido solo: lo confirma una persona.
        $this->assertSame(EstadoExpediente::Derivado, $this->expediente->fresh()->estado);

        config(['tramite.llm.url' => 'http://ollama:11434']);
        Http::fake(['ollama:11434/api/generate' => Http::response(['response' => json_encode(['responde' => true, 'motivo' => 'Adjunta la lista pedida.'])])]);
        app()->call([new EvaluarRespuesta($correo->id), 'handle']);

        $this->assertSame(['responde', 'Adjunta la lista pedida.'], [$correo->fresh()->ia_respuesta, $correo->fresh()->ia_motivo]);
        Http::assertSent(fn (Request $r) => str_contains($r['prompt'], 'Remitir la lista de matriculados.') && str_contains($r['prompt'], 'Adjunto la lista de matriculados solicitada.'));

        $this->actingAs($this->coordinador)->get("/expedientes/{$this->expediente->id}")
            ->assertInertia(fn ($p) => $p->where('expediente.correos.0.respuesta', ['area' => 'Servicios Académicos', 'ia' => 'responde', 'motivo' => 'Adjunta la lista pedida.']));
    }

    public function test_una_persona_del_area_confirma_y_queda_atendido(): void
    {
        Queue::fake();
        $correo = $this->respuesta();

        $this->actingAs(User::factory()->create()->assignRole('coordinador'))->post("/correos/{$correo->id}/atender")->assertForbidden();
        $this->actingAs($this->coordinador)->post("/correos/{$correo->id}/atender")
            ->assertInertiaFlash('toast.mensaje', 'Atendido con la respuesta por correo: ya lo atendieron todas las áreas.');

        $this->assertSame(EstadoExpediente::Atendido, $this->expediente->fresh()->estado);
        $this->assertStringContainsString('«RE: [REG-2026-00038] Para atender: Lista de matriculados 2026-II»', $this->expediente->atenciones()->sole()->nota_atencion);
        $this->assertSame('atendido', Movimiento::latest('id')->first()->tipo);
    }

    public function test_con_el_buzon_conectado_el_aviso_sale_por_gmail_aunque_el_env_diga_log(): void
    {
        Mail::fake();
        config(['mail.default' => 'log']);
        $otra = Area::factory()->create();
        AreaResponsable::create(['area_id' => $otra->id, 'user_id' => User::factory()->create()->assignRole('coordinador')->id, 'tipo' => 'titular', 'vigente_desde' => '2026-01-01']);
        app(AtencionService::class)->derivar($this->expediente->fresh(), [
            'tipo_tramite_id' => $this->expediente->fresh()->tipo_tramite_id, 'requiere_respuesta' => true,
            'areas' => [['area_id' => $this->area->id], ['area_id' => $otra->id]],
        ]);

        Mail::assertQueued(AvisoDerivacion::class, fn (AvisoDerivacion $m) => $m->mailer === 'gmail');
    }

    public function test_la_respuesta_de_un_area_no_atiende_las_demas_areas_derivadas(): void
    {
        Queue::fake();
        $otra = Area::factory()->create(['nombre' => 'Biblioteca']);
        AreaResponsable::create(['area_id' => $otra->id, 'user_id' => User::factory()->create()->assignRole('coordinador')->id, 'tipo' => 'titular', 'vigente_desde' => '2026-01-01']);
        app(AtencionService::class)->derivar($this->expediente->fresh(), [
            'tipo_tramite_id' => $this->expediente->fresh()->tipo_tramite_id, 'requiere_respuesta' => true,
            'areas' => [['area_id' => $this->area->id], ['area_id' => $otra->id]],
        ]);
        $correo = $this->respuesta();

        // Quien coordina no entra al sistema: confirma quien deriva, y solo para el área que respondió.
        $this->post("/correos/{$correo->id}/atender")->assertInertiaFlash('toast.mensaje', 'Área atendida con la respuesta por correo; faltan otras áreas.');

        $this->assertSame(['atendido', 'derivado'], [
            $this->expediente->atenciones()->where('area_id', $this->area->id)->value('estado'),
            $this->expediente->atenciones()->where('area_id', $otra->id)->value('estado'),
        ]);
    }

    public function test_sin_el_codigo_en_el_asunto_la_respuesta_llega_por_el_codigo_citado_en_el_cuerpo(): void
    {
        Queue::fake();
        $eml = implode("\r\n", [
            'From: Servicios <servicios@uni.edu.pe>',
            'To: tramite@uni.edu.pe',
            'Subject: Lista de matriculados',
            'Message-ID: <respuesta-sin-asunto@uni.edu.pe>',
            'Date: Mon, 05 Oct 2026 10:00:00 -0500',
            'Content-Type: text/plain; charset=utf-8',
            '',
            'Adjunto la lista.',
            '',
            '> Código del trámite: **REG-2026-00038** (no lo borres al responder)',
        ]);

        $this->assertSame($this->expediente->id, app(IngestaCorreoService::class)->procesar($eml, 'sin-asunto')->id);
    }

    public function test_quien_deriva_da_por_atendida_cada_area_y_cierra(): void
    {
        $otra = Area::factory()->create(['nombre' => 'Biblioteca']);
        app(AtencionService::class)->derivar($this->expediente->fresh(), [
            'tipo_tramite_id' => $this->expediente->fresh()->tipo_tramite_id, 'requiere_respuesta' => true,
            'areas' => [['area_id' => $this->area->id], ['area_id' => $otra->id]],
        ]);
        $url = "/expedientes/{$this->expediente->id}";

        $this->post("{$url}/atender", ['nota' => 'Entregó la lista en papel.', 'areas' => []])->assertSessionHasErrors('areas');
        $this->post("{$url}/atender", ['nota' => 'Entregó la lista en papel.', 'areas' => [$this->area->id]])
            ->assertInertiaFlash('toast.mensaje', 'Área atendida; faltan otras áreas.');
        $this->assertSame('derivado', $this->expediente->atenciones()->where('area_id', $otra->id)->value('estado'));

        $this->post("{$url}/atender", ['nota' => 'Respondió por teléfono.', 'areas' => [$otra->id]]);
        $this->assertSame(EstadoExpediente::Atendido, $this->expediente->fresh()->estado);

        // Ya atendido, quien deriva lo cierra.
        $this->post("{$url}/solicitar-cierre", ['nota' => 'Completo.'])->assertInertiaFlash('toast.mensaje', 'Expediente cerrado.');
        $this->assertSame(EstadoExpediente::Cerrado, $this->expediente->fresh()->estado);
    }

    public function test_la_respuesta_queda_nueva_en_la_lista_y_la_campana_hasta_que_se_atiende(): void
    {
        Queue::fake();
        $correo = $this->respuesta();
        $director = User::factory()->create()->assignRole('director');

        $this->actingAs($director)->get('/expedientes?vista=todos')->assertOk()
            ->assertInertia(fn ($p) => $p->where('expedientes.data.0.respuesta.nueva', true)->where('respuestasNuevas.0.id', $this->expediente->id));
        // Quien coordina no entra al sistema: no tiene campana.
        $this->actingAs($this->coordinador)->get('/expedientes')->assertInertia(fn ($p) => $p->where('respuestasNuevas', []));

        $this->travel(5)->minutes();
        $this->actingAs($director)->post("/correos/{$correo->id}/atender");

        $this->get('/expedientes?vista=todos')
            ->assertInertia(fn ($p) => $p->where('expedientes.data.0.respuesta.nueva', false)->where('respuestasNuevas', []));
    }
}
