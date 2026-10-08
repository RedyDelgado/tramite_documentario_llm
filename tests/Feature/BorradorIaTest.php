<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class BorradorIaTest extends TestCase
{
    use RefreshDatabase;

    private User $coordinador;

    private Expediente $expediente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $area = Area::factory()->create();
        $this->coordinador = User::factory()->create()->assignRole('coordinador');
        AreaResponsable::create(['area_id' => $area->id, 'user_id' => $this->coordinador->id, 'tipo' => 'titular', 'vigente_desde' => '2026-01-01']);
        $this->expediente = Expediente::factory()->create([
            'estado' => EstadoExpediente::EnAtencion, 'anio' => 2026, 'secuencia' => 40, 'area_principal_id' => $area->id,
            'asunto' => 'Solicitud de información de matriculados', 'remitente_nombre' => 'UGEL La Convención',
        ]);
    }

    public function test_sin_ollama_configurado_no_se_ofrece(): void
    {
        config(['tramite.llm.url' => null]);

        $this->actingAs($this->coordinador)->get("/expedientes/{$this->expediente->id}")
            ->assertInertia(fn (AssertableInertia $p) => $p->where('expediente.permisos.sugerir', false));
        $this->postJson("/expedientes/{$this->expediente->id}/borrador-ia")->assertStatus(422);
    }

    public function test_sugiere_con_el_modelo_local_lo_audita_y_solo_para_quien_responde(): void
    {
        config(['tramite.llm.url' => 'http://ollama:11434', 'tramite.llm.modelo' => 'qwen2.5:3b']);
        Http::fake(['ollama:11434/api/generate' => Http::response(['response' => "  Me dirijo a usted en atención a su solicitud.\n"])]);

        $this->actingAs($this->coordinador)->get("/expedientes/{$this->expediente->id}")
            ->assertInertia(fn (AssertableInertia $p) => $p->where('expediente.permisos.sugerir', true));
        $this->postJson("/expedientes/{$this->expediente->id}/borrador-ia")
            ->assertOk()->assertJsonPath('texto', 'Me dirijo a usted en atención a su solicitud.');

        Http::assertSent(fn (Request $r) => $r['model'] === 'qwen2.5:3b' && $r['stream'] === false
            && str_contains($r['prompt'], 'Solicitud de información de matriculados') && str_contains($r['prompt'], 'UGEL La Convención'));
        $auditoria = DB::table('auditoria')->where('accion', 'ia.borrador_sugerido')->sole();
        $this->assertSame('qwen2.5:3b', json_decode($auditoria->valor_nuevo, true)['modelo']);

        // Quien no atiende el trámite no pide borradores.
        $ajeno = User::factory()->create()->assignRole('coordinador');
        $this->actingAs($ajeno)->postJson("/expedientes/{$this->expediente->id}/borrador-ia")->assertForbidden();
    }

    public function test_si_el_modelo_no_responde_lo_dice_sin_romper(): void
    {
        config(['tramite.llm.url' => 'http://ollama:11434']);
        Http::fake(['ollama:11434/*' => fn () => throw new ConnectionException('caído')]);

        $this->actingAs($this->coordinador)->postJson("/expedientes/{$this->expediente->id}/borrador-ia")
            ->assertStatus(422)->assertJsonPath('message', 'El modelo de IA no responde. Inténtalo en unos minutos o redacta sin sugerencia.');
    }
}
