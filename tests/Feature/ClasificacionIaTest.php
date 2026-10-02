<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Jobs\ClasificarExpediente;
use App\Models\Area;
use App\Models\ClasificacionIa;
use App\Models\Configuracion;
use App\Models\Expediente;
use App\Models\TipoTramite;
use App\Models\User;
use App\Services\ClasificacionService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ClasificacionIaTest extends TestCase
{
    use RefreshDatabase;

    private User $administrativo;

    private Area $cooperacion;

    private TipoTramite $convenio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->administrativo = User::factory()->create()->assignRole('administrativo');
        $this->cooperacion = Area::factory()->create(['nombre' => 'Cooperación']);
        $this->convenio = TipoTramite::create(['nombre' => 'Convenio', 'plazo_dias' => 10]);
    }

    private function fakeIa(float $confianza = 0.95): void
    {
        Http::fake(['ai:8000/classify' => Http::response([
            'modelo' => 'intfloat/multilingual-e5-small', 'version' => 'similitud', 'texto_sha256' => 'x',
            'area' => ['id' => $this->cooperacion->id, 'confianza' => $confianza, 'metodo' => 'similitud'],
            'tipo' => ['id' => $this->convenio->id, 'confianza' => $confianza, 'metodo' => 'similitud'],
            'top_area' => [], 'top_tipo' => [],
        ])]);
    }

    private function clasificar(Expediente $e): void
    {
        (new ClasificarExpediente($e->id))->handle(app(ClasificacionService::class));
    }

    public function test_registrar_encola_la_clasificacion_sin_esperarla(): void
    {
        $expediente = Expediente::factory()->create();

        $this->actingAs($this->administrativo)->post("/expedientes/{$expediente->id}/confirmar")->assertSessionHasNoErrors();

        Queue::assertPushed(ClasificarExpediente::class, fn ($job) => $job->expedienteId === $expediente->id);
    }

    public function test_clasifica_con_el_catalogo_vigente_y_queda_registrada_en_sombra(): void
    {
        $this->fakeIa();
        Area::factory()->create(['nombre' => 'Inactiva', 'activa' => false]);
        $expediente = Expediente::factory()->create(['estado' => EstadoExpediente::Registrado, 'asunto' => 'Firma de convenio marco']);

        $this->clasificar($expediente);
        $this->clasificar($expediente);

        $clasificacion = ClasificacionIa::sole();
        $this->assertSame(['sombra', $this->cooperacion->id, 0.95], [$clasificacion->modo, $clasificacion->area_id, $clasificacion->confianza_area]);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => collect($r['areas'])->pluck('nombre')->all() === ['Cooperación'] && str_contains($r['texto'], 'Firma de convenio marco'));
        $this->assertDatabaseHas('auditoria', ['accion' => 'ia.clasificado', 'actor' => 'ia']);
    }

    public function test_en_modo_sombra_no_se_ejecuta_ni_se_muestra_nada_y_se_compara_con_la_decision_humana(): void
    {
        $this->fakeIa(0.99);
        $expediente = Expediente::factory()->create(['estado' => EstadoExpediente::Registrado]);
        $this->clasificar($expediente);

        $expediente->refresh();
        $this->assertSame([EstadoExpediente::Registrado, null, null], [$expediente->estado, $expediente->area_principal_id, $expediente->tipo_tramite_id]);
        $this->assertNull(app(ClasificacionService::class)->sugerencia($expediente));

        $otra = Area::factory()->create();
        $this->actingAs($this->administrativo)->post("/expedientes/{$expediente->id}/derivar", [
            'tipo_tramite_id' => $this->convenio->id, 'area_id' => $otra->id, 'requiere_respuesta' => true,
        ])->assertSessionHasNoErrors();

        $clasificacion = ClasificacionIa::sole();
        $this->assertSame([false, true, $otra->id], [$clasificacion->acierto_area, $clasificacion->acierto_tipo, $clasificacion->area_final_id]);
        $this->assertSame($this->administrativo->id, $clasificacion->decidido_por);
    }

    public function test_en_modo_activo_propone_solo_sobre_el_umbral(): void
    {
        Configuracion::create(['clave' => 'ia.modo', 'valor' => 'activo']);
        $expediente = Expediente::factory()->create(['estado' => EstadoExpediente::Registrado]);

        $this->fakeIa(0.95);
        $this->clasificar($expediente);
        $this->assertSame(['area_id' => $this->cooperacion->id, 'tipo_tramite_id' => $this->convenio->id, 'confianza_area' => 0.95, 'confianza_tipo' => 0.95, 'alta' => true],
            app(ClasificacionService::class)->sugerencia($expediente));

        // Bajo el umbral de sugerencia no se propone nada.
        ClasificacionIa::query()->update(['confianza_area' => 0.4, 'confianza_tipo' => 0.4]);
        $this->assertNull(app(ClasificacionService::class)->sugerencia($expediente));
    }

    public function test_si_la_ia_esta_caida_el_registro_sigue_y_el_job_reintenta(): void
    {
        Http::fake(['ai:8000/classify' => fn () => throw new ConnectionException('caída')]);
        $expediente = Expediente::factory()->create();

        $this->actingAs($this->administrativo)->post("/expedientes/{$expediente->id}/confirmar")->assertSessionHasNoErrors();
        $this->assertSame(EstadoExpediente::Registrado, $expediente->fresh()->estado);

        $this->expectException(ConnectionException::class);
        $this->clasificar($expediente);
    }
}
