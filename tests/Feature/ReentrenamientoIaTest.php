<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Models\Area;
use App\Models\ClasificacionIa;
use App\Models\CorreccionPendiente;
use App\Models\Expediente;
use App\Models\TipoTramite;
use App\Services\ClasificacionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReentrenamientoIaTest extends TestCase
{
    use RefreshDatabase;

    private Area $a;

    private Area $b;

    private TipoTramite $tipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->a = Area::factory()->create();
        $this->b = Area::factory()->create();
        $this->tipo = TipoTramite::create(['nombre' => 'Convenio']);
    }

    private function decidida(string $asunto, array $datos): ClasificacionIa
    {
        $c = ClasificacionIa::create([
            'expediente_id' => Expediente::factory()->create(['asunto' => $asunto, 'estado' => EstadoExpediente::Derivado])->id,
            'modelo' => 'e5', 'version' => 'similitud', 'texto_sha256' => str_repeat('a', 64), 'resultado' => [],
            'area_id' => $this->a->id, 'tipo_tramite_id' => $this->tipo->id, 'modo' => 'sombra', 'created_at' => now(),
        ]);
        $c->forceFill($datos + ['decidido_at' => now()])->save();

        return $c;
    }

    public function test_solo_se_reentrena_con_etiquetas_confirmadas_por_personas(): void
    {
        $this->decidida('Acertó todo', ['area_final_id' => $this->a->id, 'tipo_final_id' => $this->tipo->id, 'acierto_area' => true, 'acierto_tipo' => true]);
        $validada = $this->decidida('Corrección validada', ['area_final_id' => $this->b->id, 'acierto_area' => false, 'acierto_tipo' => null]);
        CorreccionPendiente::create(['clasificacion_id' => $validada->id, 'campo' => 'area', 'valor_ia' => $this->a->id, 'valor_humano' => $this->b->id])
            ->forceFill(['estado' => 'validada'])->save();
        $pendiente = $this->decidida('Corrección sin validar', ['area_final_id' => $this->b->id, 'acierto_area' => false]);
        CorreccionPendiente::create(['clasificacion_id' => $pendiente->id, 'campo' => 'area', 'valor_ia' => $this->a->id, 'valor_humano' => $this->b->id]);
        // La IA propuso pero nadie decidió todavía: no es una etiqueta.
        ClasificacionIa::create([
            'expediente_id' => Expediente::factory()->create()->id, 'modelo' => 'e5', 'version' => 'similitud', 'texto_sha256' => str_repeat('b', 64),
            'resultado' => [], 'area_id' => $this->a->id, 'modo' => 'sombra', 'created_at' => now(),
        ]);

        $ejemplos = collect(app(ClasificacionService::class)->ejemplosValidados())->map(fn ($e) => [strtok($e['texto'], "\n"), $e['area_id'], $e['tipo_id']])->sortBy(0)->values()->all();

        $this->assertSame([
            ['Acertó todo', $this->a->id, $this->tipo->id],
            ['Corrección validada', $this->b->id, null],
        ], $ejemplos);
    }

    public function test_reentrenar_activa_la_nueva_version_y_revertir_es_un_comando(): void
    {
        $this->decidida('Firma de convenio', ['area_final_id' => $this->a->id, 'acierto_area' => true]);
        Http::fake([
            'ai:8000/train' => Http::response(['version' => 'v20261005120000', 'ejemplos' => 1, 'clases' => ['area' => [1, 2]]]),
            'ai:8000/models/activate' => fn (Request $r) => $r['version'] === 'v1'
                ? Http::response(['detail' => 'Versión inexistente'], 404)
                : Http::response(['activa' => $r['version']]),
        ]);

        $this->artisan('ia:reentrenar')->expectsOutputToContain('Versión v20261005120000 activa')->assertSuccessful();
        $this->artisan('ia:modelo', ['version' => 'v20261001000000'])->expectsOutput('Activa: v20261001000000')->assertSuccessful();
        $this->artisan('ia:modelo', ['--similitud' => true])->expectsOutput('Activa: similitud (sin entrenar)')->assertSuccessful();
        $this->artisan('ia:modelo', ['version' => 'v1'])->expectsOutput('No existe la versión v1.')->assertFailed();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/train') && $r['ejemplos'][0]['area_id'] === $this->a->id);
        $this->assertDatabaseHas('auditoria', ['accion' => 'ia.reentrenado']);
        $this->assertDatabaseHas('auditoria', ['accion' => 'ia.modelo_activado']);
    }

    public function test_sin_datos_suficientes_el_comando_lo_explica(): void
    {
        Http::fake(['ai:8000/train' => Http::response(['detail' => 'Hacen falta al menos 2 categorías con 3 ejemplos validados cada una.'], 422)]);

        $this->artisan('ia:reentrenar')->expectsOutput('Hacen falta al menos 2 categorías con 3 ejemplos validados cada una.')->assertFailed();
    }
}
