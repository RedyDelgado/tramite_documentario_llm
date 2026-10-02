<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Models\Area;
use App\Models\ClasificacionIa;
use App\Models\Configuracion;
use App\Models\CorreccionPendiente;
use App\Models\Expediente;
use App\Models\TipoTramite;
use App\Models\User;
use App\Services\ClasificacionService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PanelIaTest extends TestCase
{
    use RefreshDatabase;

    private User $administrativo;

    private User $director;

    private Area $acertada;

    private Area $otra;

    private TipoTramite $tipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        Http::fake(['ai:8000/model-info' => Http::response(['version' => null, 'entrenado_en' => null, 'ejemplos' => null])]);
        $this->administrativo = User::factory()->create()->assignRole('administrativo');
        $this->director = User::factory()->create()->assignRole('director');
        $this->acertada = Area::factory()->create(['nombre' => 'Cooperación']);
        $this->otra = Area::factory()->create(['nombre' => 'Logística']);
        $this->tipo = TipoTramite::create(['nombre' => 'Convenio', 'plazo_dias' => 5]);
    }

    /** La IA propone «Cooperación» y la persona deriva al área indicada. */
    private function decidido(Area $area): ClasificacionIa
    {
        $expediente = Expediente::factory()->create(['estado' => EstadoExpediente::Registrado]);
        ClasificacionIa::create([
            'expediente_id' => $expediente->id, 'modelo' => 'e5', 'version' => 'similitud', 'texto_sha256' => str_repeat('a', 64),
            'resultado' => [], 'area_id' => $this->acertada->id, 'confianza_area' => 0.8, 'tipo_tramite_id' => $this->tipo->id,
            'confianza_tipo' => 0.8, 'modo' => 'sombra', 'created_at' => now(),
        ]);
        $this->actingAs($this->administrativo)->post("/expedientes/{$expediente->id}/derivar", [
            'tipo_tramite_id' => $this->tipo->id, 'area_id' => $area->id, 'requiere_respuesta' => true,
        ])->assertSessionHasNoErrors();

        return ClasificacionIa::where('expediente_id', $expediente->id)->sole();
    }

    public function test_se_mide_el_porcentaje_de_acierto_por_categoria(): void
    {
        $this->decidido($this->acertada);
        $this->decidido($this->acertada);
        $this->decidido($this->otra);

        $precision = app(ClasificacionService::class)->precision();

        $this->assertSame([
            ['nombre' => 'Cooperación', 'total' => 2, 'aciertos' => 2],
            ['nombre' => 'Logística', 'total' => 1, 'aciertos' => 0],
        ], $precision['area']);
        $this->assertSame([['nombre' => 'Convenio', 'total' => 3, 'aciertos' => 3]], $precision['tipo']);
        $this->assertSame([['version' => 'similitud', 'modo' => 'sombra', 'total' => 3, 'aciertos_area' => 2, 'aciertos_tipo' => 3]], $precision['versiones']);
    }

    public function test_una_contradiccion_queda_como_correccion_que_valida_otra_persona(): void
    {
        $this->decidido($this->otra);
        $correccion = CorreccionPendiente::sole();
        $this->assertSame(['area', $this->acertada->id, $this->otra->id, $this->administrativo->id], [$correccion->campo, $correccion->valor_ia, $correccion->valor_humano, $correccion->usuario_id]);

        $this->actingAs($this->administrativo)->post("/ia/correcciones/{$correccion->id}", ['validar' => true])->assertForbidden();
        $this->actingAs($this->director)->post("/ia/correcciones/{$correccion->id}", ['validar' => true])->assertSessionHasNoErrors();

        $this->assertSame('validada', $correccion->fresh()->estado);
        $this->assertDatabaseHas('auditoria', ['accion' => 'ia.correccion_validada']);
        $this->actingAs($this->director)->post("/ia/correcciones/{$correccion->id}", ['validar' => false])->assertInertiaFlash('toast.tipo', 'error');
    }

    public function test_el_panel_lo_ven_quienes_validan_o_configuran(): void
    {
        $this->decidido($this->otra);

        $this->actingAs($this->director)->get('/ia')->assertOk()
            ->assertInertia(fn ($page) => $page->component('ia/Index')->where('correcciones.meta.total', 1)->where('correcciones.data.0.puede_resolver', true));
        $this->actingAs(User::factory()->create()->assignRole('superadmin'))->get('/ia')->assertOk();
        $this->actingAs(User::factory()->create()->assignRole('coordinador'))->get('/ia')->assertForbidden();
    }

    public function test_el_modo_y_los_umbrales_de_la_ia_se_configuran_desde_el_panel(): void
    {
        $admin = User::factory()->create()->assignRole('superadmin');
        $datos = ['porcentaje_amarillo' => 30, 'dias_sin_movimiento' => 5, 'ia_modo' => 'activo', 'ia_umbral_sugerencia' => 0.7, 'ia_umbral_alta' => 0.95];

        $this->actingAs($admin)->put('/umbrales', $datos)->assertSessionHasNoErrors();
        $this->assertSame(['activo', 0.7], [Configuracion::valor('ia.modo'), Configuracion::valor('ia.umbral_sugerencia')]);

        $this->actingAs($admin)->put('/umbrales', [...$datos, 'ia_umbral_alta' => 0.5])->assertSessionHasErrors('ia_umbral_alta');
    }

    public function test_las_listas_paginadas_llegan_con_meta_y_links(): void
    {
        $admin = User::factory()->create()->assignRole('superadmin');

        foreach (['emisores' => 'emisores', 'tipos-documento' => 'tipos', 'instrucciones' => 'instrucciones', 'reglas-no-tramite' => 'reglas', 'ubicaciones' => 'ubicaciones'] as $ruta => $prop) {
            $this->actingAs($admin)->get("/{$ruta}")->assertOk()
                ->assertInertia(fn ($page) => $page->has("{$prop}.meta.total")->has("{$prop}.links.next"));
        }
    }
}
