<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Mail\ResumenDiario;
use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\Expediente;
use App\Models\Movimiento;
use App\Models\TipoTramite;
use App\Models\User;
use App\Services\AreaService;
use App\Services\PanelService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AreasCopiaTest extends TestCase
{
    use RefreshDatabase;

    private User $administrativo;

    private Area $responsable;

    private Area $copia;

    private User $coordinadorCopia;

    private TipoTramite $tipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->travelTo('2026-10-05 09:00:00');
        $this->administrativo = User::factory()->create()->assignRole('administrativo');
        [$this->responsable, $this->copia] = Area::factory()->count(2)->create();
        $this->coordinadorCopia = User::factory()->create()->assignRole('coordinador');
        AreaResponsable::create(['area_id' => $this->copia->id, 'user_id' => $this->coordinadorCopia->id, 'tipo' => 'titular', 'vigente_desde' => '2026-01-01']);
        $this->tipo = TipoTramite::create(['nombre' => 'Requerimiento', 'plazo_dias' => 3, 'tipo_dias' => 'habiles']);
    }

    private function derivar(Expediente $expediente, array $copias, ?Area $area = null): TestResponse
    {
        return $this->actingAs($this->administrativo)->post("/expedientes/{$expediente->id}/derivar", [
            'tipo_tramite_id' => $this->tipo->id, 'area_id' => ($area ?? $this->responsable)->id, 'requiere_respuesta' => true,
            'areas_copia' => array_map(fn (Area $a) => $a->id, $copias),
        ]);
    }

    private function registrado(): Expediente
    {
        static $secuencia = 40;

        return Expediente::factory()->create(['estado' => EstadoExpediente::Registrado, 'fecha_ingreso' => now(), 'anio' => 2026, 'secuencia' => $secuencia++]);
    }

    public function test_el_area_en_copia_lo_ve_pero_no_lo_atiende_ni_le_suma_pendientes(): void
    {
        Mail::fake();
        $expediente = $this->registrado();
        $this->derivar($expediente, [$this->copia])->assertSessionHasNoErrors();

        $this->actingAs($this->coordinadorCopia)->get("/expedientes/{$expediente->id}")->assertOk()
            ->assertInertia(fn ($page) => $page->where('expediente.areas_copia.0.nombre', $this->copia->nombre)
                ->where('expediente.permisos.tomar', false)->where('expediente.permisos.solicitar_cierre', false));
        $this->actingAs($this->coordinadorCopia)->post("/expedientes/{$expediente->id}/tomar")->assertForbidden();
        $this->assertContains("area:{$this->copia->id}", $expediente->fresh()->toSearchableArray()['visible_para']);

        // Mira, pero no es pendiente suyo: ni en el panel ni en el resumen diario.
        $this->assertSame(0, app(PanelService::class)->indicadores($this->coordinadorCopia)['abiertos']);
        $this->artisan('resumen:diario')->assertSuccessful();
        Mail::assertNotQueued(ResumenDiario::class);
    }

    public function test_reasignar_reemplaza_las_copias_y_queda_en_el_historial(): void
    {
        $otra = Area::factory()->create(['nombre' => 'Biblioteca']);
        $expediente = $this->registrado();
        $this->derivar($expediente, [$this->copia, $otra]);

        // El área que pasa a ser responsable deja de estar en copia.
        $this->derivar($expediente, [$this->copia, $otra], area: $otra)->assertSessionHasNoErrors();

        $this->assertSame([$this->copia->id], $expediente->fresh()->areasCopia->pluck('id')->all());
        // Los nombres quedan en orden alfabético, tal como estaban al derivar.
        $this->assertSame([collect([$this->copia->nombre, 'Biblioteca'])->sort()->values()->all(), [$this->copia->nombre]], Movimiento::orderBy('id')->pluck('areas_copia')->all());
        $this->actingAs($this->administrativo)->get("/expedientes/{$expediente->id}")
            ->assertInertia(fn ($page) => $page->where('historial', fn ($h) => collect($h)->contains(fn ($e) => str_contains((string) $e['detalle'], "Copia a {$this->copia->nombre}"))));

        $this->derivar($expediente, [], area: $otra);
        $this->assertCount(0, $expediente->fresh()->areasCopia);
        $this->assertFalse(Expediente::visiblesPara($this->coordinadorCopia)->whereKey($expediente->id)->exists());
    }

    public function test_fusionar_un_area_pasa_sus_copias_al_destino(): void
    {
        $destino = Area::factory()->create();
        $enCopia = $this->registrado();
        $this->derivar($enCopia, [$this->copia]);
        // El destino ya es responsable de este: no queda además en copia.
        $delDestino = $this->registrado();
        $this->derivar($delDestino, [$this->copia], area: $destino);

        app(AreaService::class)->fusionar($this->copia, $destino);

        $this->assertSame([$destino->id], $enCopia->fresh()->areasCopia->pluck('id')->all());
        $this->assertCount(0, $delDestino->fresh()->areasCopia);
    }

    public function test_no_se_copia_a_un_area_inactiva(): void
    {
        $this->derivar($this->registrado(), [Area::factory()->create(['activa' => false])])->assertSessionHasErrors('areas_copia.0');
    }
}
