<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** El chat con la IA responde con los expedientes que quien pregunta ve, y solo lo usan administrativo y director. */
class AsistenteIaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        config(['tramite.llm.url' => 'http://ollama:11434']);
    }

    public function test_responde_con_los_expedientes_encontrados_y_los_cita(): void
    {
        $e = Expediente::factory()->create(['estado' => EstadoExpediente::Registrado, 'anio' => 2026, 'secuencia' => 47, 'asunto' => 'Carta circular de matrícula extemporánea']);
        // Sin número no tiene código: no cuenta como citado aunque también se encuentre.
        Expediente::factory()->create(['estado' => EstadoExpediente::PorRevisar, 'secuencia' => null, 'asunto' => 'Consulta sobre matrícula']);
        Http::fake(['ollama:11434/api/generate' => Http::response(['response' => 'El trámite [REG-2026-00047] está registrado.'])]);

        $this->actingAs(User::factory()->create()->assignRole('director'))
            ->postJson('/asistente-ia', ['pregunta' => '¿Qué pasó con la carta de matrícula?'])
            ->assertOk()
            ->assertJson(['respuesta' => 'El trámite [REG-2026-00047] está registrado.', 'fuentes' => [['id' => $e->id, 'asunto' => $e->asunto]]])
            ->assertJsonCount(1, 'fuentes');
        Http::assertSent(fn (Request $r) => str_contains($r['prompt'], '[REG-2026-00047] Carta circular de matrícula extemporánea'));
    }

    public function test_sin_resultados_no_pregunta_al_modelo(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create()->assignRole('administrativo'))
            ->postJson('/asistente-ia', ['pregunta' => 'presupuesto de laboratorio'])
            ->assertOk()->assertJson(['fuentes' => []]);
        Http::assertNothingSent();
    }

    public function test_coordinadores_y_docentes_no_lo_usan(): void
    {
        foreach (['coordinador', 'otros'] as $rol) {
            $this->actingAs(User::factory()->create()->assignRole($rol))->postJson('/asistente-ia', ['pregunta' => 'hola'])->assertForbidden();
        }
    }
}
