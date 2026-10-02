<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Models\Area;
use App\Models\Emisor;
use App\Models\Expediente;
use App\Models\Grupo;
use App\Models\Movimiento;
use App\Models\TipoTramite;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SerieTest extends TestCase
{
    use RefreshDatabase;

    private User $administrativo;

    /** @var list<Expediente> */
    private array $circulares;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->travelTo('2026-08-17 10:00:00');
        $this->administrativo = User::factory()->create()->assignRole('administrativo');
        $emisor = Emisor::create(['nombre' => 'Vicerrectorado Académico', 'tipo' => 'interno']);
        // El día pico del registro: varios oficios circulares del mismo emisor y tema (14.1).
        $this->circulares = Expediente::factory()->count(3)->sequence(fn ($s) => ['secuencia' => 40 + $s->index])->create([
            'estado' => EstadoExpediente::Registrado, 'anio' => 2026, 'emisor_id' => $emisor->id,
            'asunto' => 'Encuesta de sostenibilidad', 'fecha_ingreso' => now(),
        ])->all();
        Expediente::factory()->create(['estado' => EstadoExpediente::Registrado, 'emisor_id' => $emisor->id, 'asunto' => 'Otro tema', 'fecha_ingreso' => now()]);
    }

    public function test_se_proponen_los_documentos_parecidos_y_se_agrupan_sin_perder_cada_registro(): void
    {
        [$a, $b, $c] = $this->circulares;

        $this->actingAs($this->administrativo)->get("/expedientes/{$a->id}")
            ->assertInertia(fn ($page) => $page->where('agrupacion.parecidos', fn ($p) => collect($p)->pluck('id')->all() === [$b->id, $c->id]));

        $this->actingAs($this->administrativo)->post("/expedientes/{$a->id}/serie", ['nombre' => 'Encuesta de sostenibilidad', 'incluir' => [$b->id, $c->id]])
            ->assertSessionHasNoErrors();

        $grupo = Grupo::sole();
        $this->assertSame([$a->id, $b->id, $c->id], $grupo->expedientes()->orderBy('id')->pluck('id')->all());
        $this->assertSame(['N°00040', 'N°00041', 'N°00042'], $grupo->expedientes()->orderBy('id')->get()->pluck('numero_registro')->all());
        $this->assertSame(3, DB::table('auditoria')->where('accion', 'expediente.agrupado')->count());
    }

    public function test_una_serie_se_deriva_una_sola_vez_para_todo_el_lote(): void
    {
        [$a, $b, $c] = $this->circulares;
        $this->actingAs($this->administrativo)->post("/expedientes/{$a->id}/serie", ['nombre' => 'Encuesta', 'incluir' => [$b->id, $c->id]]);
        $area = Area::factory()->create();

        $this->actingAs($this->administrativo)->post("/expedientes/{$a->id}/derivar", [
            'tipo_tramite_id' => TipoTramite::create(['nombre' => 'Requerimiento', 'plazo_dias' => 5])->id,
            'area_id' => $area->id, 'requiere_respuesta' => true, 'instruccion' => 'Presentar información', 'toda_la_serie' => true,
        ])->assertInertiaFlash('toast.mensaje', 'Serie derivada: 3 expedientes.');

        foreach ([$a, $b, $c] as $e) {
            $this->assertSame([EstadoExpediente::Derivado, $area->id], [$e->fresh()->estado, $e->fresh()->area_principal_id]);
        }
        $this->assertSame(3, Movimiento::where('tipo', 'derivacion')->count());
    }

    public function test_quitar_de_la_serie_queda_auditado(): void
    {
        [$a, $b] = $this->circulares;
        $this->actingAs($this->administrativo)->post("/expedientes/{$a->id}/serie", ['nombre' => 'Encuesta', 'incluir' => [$b->id]]);

        $this->actingAs($this->administrativo)->delete("/expedientes/{$b->id}/serie")->assertSessionHasNoErrors();

        $this->assertNull($b->fresh()->grupo_id);
        $this->assertDatabaseHas('auditoria', ['accion' => 'expediente.desagrupado']);
    }

    public function test_un_coordinador_no_agrupa(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('coordinador'))
            ->post("/expedientes/{$this->circulares[0]->id}/serie", ['nombre' => 'X'])->assertForbidden();
    }
}
