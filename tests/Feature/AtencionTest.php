<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\Expediente;
use App\Models\Movimiento;
use App\Models\TipoTramite;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AtencionTest extends TestCase
{
    use RefreshDatabase;

    private User $administrativo;

    private User $director;

    private User $coordinador;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->administrativo = User::factory()->create()->assignRole('administrativo');
        $this->director = User::factory()->create()->assignRole('director');
        $this->coordinador = User::factory()->create()->assignRole('coordinador');
        $this->area = Area::factory()->create();
        AreaResponsable::create(['area_id' => $this->area->id, 'user_id' => $this->coordinador->id, 'tipo' => 'titular', 'vigente_desde' => '2026-01-01']);
        $this->travelTo('2026-10-05 09:00:00');
    }

    private function registrado(): Expediente
    {
        return Expediente::factory()->create(['estado' => EstadoExpediente::Registrado, 'fecha_ingreso' => now(), 'registrado_por' => $this->administrativo->id]);
    }

    private function derivar(Expediente $expediente, TipoTramite $tipo, array $extra = [], ?User $quien = null): TestResponse
    {
        return $this->actingAs($quien ?? $this->administrativo)->post("/expedientes/{$expediente->id}/derivar", $extra + [
            'tipo_tramite_id' => $tipo->id, 'area_id' => $this->area->id, 'responsable_id' => null,
            'requiere_respuesta' => true, 'instruccion' => 'Atender', 'nota' => null, 'fecha_limite' => null,
        ]);
    }

    public function test_derivar_copia_el_plazo_y_una_fecha_del_documento_manda(): void
    {
        $tipo = TipoTramite::create(['nombre' => 'Requerimiento', 'plazo_dias' => 3, 'tipo_dias' => 'habiles']);
        $expediente = $this->registrado();

        $this->derivar($expediente, $tipo)->assertSessionHasNoErrors();
        $expediente->refresh();
        $this->assertSame([EstadoExpediente::Derivado, 3, '2026-10-08'], [$expediente->estado, $expediente->plazo_dias_aplicado, $expediente->fecha_limite->toDateString()]);
        $this->assertTrue(Expediente::visiblesPara($this->coordinador)->whereKey($expediente->id)->exists());

        $movimiento = Movimiento::sole();
        $this->assertSame(['derivacion', $this->area->id, 'Atender', '2026-10-08'], [$movimiento->tipo, $movimiento->a_area_id, $movimiento->instruccion, $movimiento->fecha_limite->toDateString()]);
        $auditoria = DB::table('auditoria')->where('accion', 'expediente.derivado')->sole();
        $this->assertSame(['estado' => 'registrado'], array_intersect_key(json_decode($auditoria->valor_anterior, true), ['estado' => 1]));

        // Reasignar con una fecha fijada por el documento (reunión del 20).
        $this->derivar($expediente, $tipo, ['fecha_limite' => '2026-10-20'], $this->director)->assertSessionHasNoErrors();
        $this->assertSame('2026-10-20', $expediente->fresh()->fecha_limite->toDateString());
        $this->derivar($expediente, $tipo, ['fecha_limite' => '2026-10-01'])->assertSessionHasErrors('fecha_limite');
    }

    public function test_para_conocimiento_sin_plazo_tomarlo_lo_deja_atendido(): void
    {
        $tipo = TipoTramite::create(['nombre' => 'Comunicación informativa', 'plazo_dias' => null]);
        $expediente = $this->registrado();
        $this->derivar($expediente, $tipo, ['requiere_respuesta' => false]);

        $this->actingAs($this->coordinador)->post("/expedientes/{$expediente->id}/tomar")->assertSessionHasNoErrors();

        $expediente->refresh();
        $this->assertSame(EstadoExpediente::Atendido, $expediente->estado);
        $this->assertNotNull($expediente->atendido_at);
        $this->assertNull($expediente->semaforo);
    }

    public function test_el_cierre_con_aprobacion_lo_aprueba_el_rol_del_tipo_y_nunca_quien_registro_o_lo_pidio(): void
    {
        $tipo = TipoTramite::create(['nombre' => 'Solicitud de recursos', 'plazo_dias' => 5, 'aprueba_cierre' => 'director']);
        $expediente = $this->registrado();
        $this->derivar($expediente, $tipo);
        $this->actingAs($this->coordinador)->post("/expedientes/{$expediente->id}/tomar");

        $this->actingAs($this->coordinador)->post("/expedientes/{$expediente->id}/solicitar-cierre", ['nota' => 'Se entregaron los recursos.'])->assertSessionHasNoErrors();
        $this->assertSame(EstadoExpediente::EnAtencion, $expediente->fresh()->estado);

        $this->actingAs($this->coordinador)->post("/expedientes/{$expediente->id}/resolver-cierre", ['aprobar' => true])->assertForbidden();
        $this->actingAs($this->administrativo)->post("/expedientes/{$expediente->id}/resolver-cierre", ['aprobar' => true])->assertForbidden();
        $this->actingAs($this->director)->post("/expedientes/{$expediente->id}/resolver-cierre", ['aprobar' => false])->assertSessionHasErrors('nota');
        $this->actingAs($this->director)->post("/expedientes/{$expediente->id}/resolver-cierre", ['aprobar' => true])->assertSessionHasNoErrors();

        $expediente->refresh();
        $this->assertSame(EstadoExpediente::Cerrado, $expediente->estado);
        $this->assertNotNull($expediente->atendido_at);
        $this->assertSame(['derivacion', 'en_atencion', 'cierre_solicitado', 'cierre_aprobado'], Movimiento::orderBy('id')->pluck('tipo')->all());
    }

    public function test_si_quien_atiende_ya_aprueba_el_cierre_se_cierra_y_si_es_un_docente_espera_al_coordinador(): void
    {
        $tipo = TipoTramite::create(['nombre' => 'Reserva de matrícula', 'plazo_dias' => 5, 'aprueba_cierre' => 'coordinador']);

        // El coordinador lo atiende en persona: su aprobación sería la suya.
        $propio = $this->registrado();
        $this->derivar($propio, $tipo);
        $this->actingAs($this->coordinador)->post("/expedientes/{$propio->id}/solicitar-cierre", ['nota' => 'Reserva registrada.'])->assertSessionHasNoErrors();
        $this->assertSame(EstadoExpediente::Cerrado, $propio->fresh()->estado);

        // Lo atiende un docente asignado: el cierre espera al coordinador del área.
        $docente = User::factory()->create()->assignRole('otros');
        $asignado = $this->registrado();
        $this->derivar($asignado, $tipo, ['responsable_id' => $docente->id]);
        $this->actingAs($docente)->post("/expedientes/{$asignado->id}/solicitar-cierre", ['nota' => 'Revisado.'])->assertSessionHasNoErrors();
        $this->assertSame(EstadoExpediente::Derivado, $asignado->fresh()->estado);
        $this->actingAs($this->coordinador)->post("/expedientes/{$asignado->id}/resolver-cierre", ['aprobar' => true])->assertSessionHasNoErrors();
        $this->assertSame(EstadoExpediente::Cerrado, $asignado->fresh()->estado);
    }

    public function test_el_superadmin_titular_de_un_area_no_atiende_sus_tramites(): void
    {
        $superadmin = User::factory()->create()->assignRole('superadmin');
        AreaResponsable::create(['area_id' => $this->area->id, 'user_id' => $superadmin->id, 'tipo' => 'suplente', 'vigente_desde' => '2026-01-01']);
        $expediente = $this->registrado();
        $this->derivar($expediente, TipoTramite::create(['nombre' => 'Reserva', 'plazo_dias' => 5, 'aprueba_cierre' => 'coordinador']));

        $this->assertFalse($superadmin->can('atender', $expediente->fresh()));
        $this->assertFalse($superadmin->can('cerrarSinAprobacion', $expediente->fresh()));
    }

    public function test_sin_aprobacion_configurada_solicitar_el_cierre_ya_cierra(): void
    {
        $tipo = TipoTramite::create(['nombre' => 'Invitación', 'plazo_dias' => 3]);
        $expediente = $this->registrado();
        $this->derivar($expediente, $tipo);

        $this->actingAs($this->coordinador)->post("/expedientes/{$expediente->id}/solicitar-cierre")->assertSessionHasNoErrors();

        $this->assertSame(EstadoExpediente::Cerrado, $expediente->fresh()->estado);
    }

    public function test_un_estado_que_no_admite_el_paso_se_rechaza(): void
    {
        $expediente = Expediente::factory()->create(['estado' => EstadoExpediente::PorRevisar]);
        $tipo = TipoTramite::create(['nombre' => 'Invitación', 'plazo_dias' => 3]);

        $this->derivar($expediente, $tipo)->assertInertiaFlash('toast.tipo', 'error');
        $this->assertSame(0, Movimiento::count());
    }
}
