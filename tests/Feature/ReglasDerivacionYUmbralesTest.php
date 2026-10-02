<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Configuracion;
use App\Models\Expediente;
use App\Models\ReglaDerivacion;
use App\Models\TipoTramite;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReglasDerivacionYUmbralesTest extends TestCase
{
    use RefreshDatabase;

    // Valores por defecto de la IA: este test solo cambia el semáforo.
    private const IA = ['ia_modo' => 'sombra', 'ia_umbral_sugerencia' => 0.6, 'ia_umbral_alta' => 0.9];

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->admin = User::factory()->create()->assignRole('superadmin');
    }

    /** @param array<string, mixed> $datos */
    private function crearRegla(array $datos): TestResponse
    {
        return $this->actingAs($this->admin)->post('/reglas-derivacion', $datos + [
            'nombre' => 'Regla', 'tipo_tramite_id' => null, 'palabras_clave' => [], 'remitentes' => [],
            'responsable_id' => null, 'prioridad' => 100, 'activa' => true,
        ]);
    }

    public function test_la_primera_regla_activa_por_prioridad_que_cumple_todas_sus_condiciones(): void
    {
        $tipo = TipoTramite::create(['nombre' => 'Convenio', 'plazo_dias' => 10]);
        [$cooperacion, $direccion, $legal] = Area::factory()->count(3)->create();

        $this->crearRegla(['nombre' => 'Convenios del ministerio', 'tipo_tramite_id' => $tipo->id, 'remitentes' => ['Minedu.gob.pe'], 'area_destino_id' => $direccion->id, 'prioridad' => 10])
            ->assertRedirect('/reglas-derivacion');
        $this->crearRegla(['nombre' => 'Convenios', 'palabras_clave' => ['convenio'], 'area_destino_id' => $cooperacion->id, 'prioridad' => 20]);
        $this->crearRegla(['nombre' => 'Inactiva', 'palabras_clave' => ['convenio'], 'area_destino_id' => $legal->id, 'prioridad' => 1, 'activa' => false]);

        $delMinisterio = Expediente::factory()->create(['asunto' => 'Firma de CONVENIO marco', 'remitente_email' => 'oficina@sede.minedu.gob.pe', 'tipo_tramite_id' => $tipo->id]);
        $deOtro = Expediente::factory()->create(['asunto' => 'Firma de convenio marco', 'remitente_email' => 'ana@otro.pe', 'tipo_tramite_id' => $tipo->id]);
        $sinRegla = Expediente::factory()->create(['asunto' => 'Solicitud de constancia', 'remitente_email' => 'ana@otro.pe']);

        $this->assertSame($direccion->id, ReglaDerivacion::primeraQueAplica($delMinisterio)?->area_destino_id);
        $this->assertSame($cooperacion->id, ReglaDerivacion::primeraQueAplica($deOtro)?->area_destino_id);
        $this->assertNull(ReglaDerivacion::primeraQueAplica($sinRegla));
        $this->assertDatabaseHas('auditoria', ['accion' => 'regla_derivacion.creada']);
    }

    public function test_un_dominio_no_coincide_con_otro_que_solo_termina_igual(): void
    {
        $regla = new ReglaDerivacion(['condicion' => ['palabras_clave' => [], 'remitentes' => ['minedu.gob.pe']]]);

        $this->assertFalse($regla->aplica(new Expediente(['remitente_email' => 'x@falsominedu.gob.pe'])));
        $this->assertTrue($regla->aplica(new Expediente(['remitente_email' => 'x@minedu.gob.pe'])));
    }

    public function test_una_regla_necesita_al_menos_una_condicion_y_remitentes_validos(): void
    {
        $area = Area::factory()->create();

        $this->crearRegla(['area_destino_id' => $area->id])->assertSessionHasErrors('tipo_tramite_id');
        $this->crearRegla(['area_destino_id' => $area->id, 'remitentes' => ['no es correo']])->assertSessionHasErrors('remitentes.0');
        $this->assertSame(0, ReglaDerivacion::count());
    }

    public function test_los_umbrales_rigen_sin_desplegar_y_solo_se_audita_lo_que_cambia(): void
    {
        $this->assertSame(30, Configuracion::valor('semaforo.porcentaje_amarillo'));

        $this->actingAs($this->admin)->put('/umbrales', ['porcentaje_amarillo' => 25, 'dias_sin_movimiento' => 5, ...self::IA])->assertSessionHasNoErrors();

        $this->assertSame(25, Configuracion::valor('semaforo.porcentaje_amarillo'));
        $cambio = DB::table('auditoria')->where('accion', 'configuracion.actualizada')->sole();
        $this->assertSame(['semaforo.porcentaje_amarillo' => 30], json_decode($cambio->valor_anterior, true));
        $this->assertSame(['semaforo.porcentaje_amarillo' => 25], json_decode($cambio->valor_nuevo, true));

        $this->actingAs($this->admin)->put('/umbrales', ['porcentaje_amarillo' => 100, 'dias_sin_movimiento' => 0, ...self::IA])
            ->assertSessionHasErrors(['porcentaje_amarillo', 'dias_sin_movimiento']);
    }

    public function test_sin_permiso_de_configuracion_no_se_editan_reglas_ni_umbrales(): void
    {
        $director = User::factory()->create()->assignRole('director');

        $this->actingAs($director)->get('/reglas-derivacion')->assertForbidden();
        $this->actingAs($director)->get('/umbrales')->assertForbidden();
        $this->actingAs($director)->put('/umbrales', ['porcentaje_amarillo' => 10, 'dias_sin_movimiento' => 2])->assertForbidden();
    }
}
