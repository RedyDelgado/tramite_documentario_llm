<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\Expediente;
use App\Models\User;
use App\Services\IngestaCorreoService;
use Database\Seeders\ReglasNoTramiteSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Meilisearch\Client;
use Meilisearch\Contracts\TasksQuery;
use Tests\TestCase;
use Throwable;

/** Contra el Meilisearch real (stack local y CI): filtro de permisos, errores de tipeo y < 1 s (7.4). */
class BusquedaMeilisearchTest extends TestCase
{
    use RefreshDatabase;

    private Client $meili;

    private User $administrativo;

    protected function setUp(): void
    {
        parent::setUp();
        config(['scout.driver' => 'meilisearch', 'scout.prefix' => 'test_']);
        $this->meili = app(Client::class);

        try {
            $this->meili->health();
        } catch (Throwable) {
            $this->markTestSkipped('Meilisearch no está disponible.');
        }

        $this->meili->deleteIndex('test_expedientes');
        $this->esperarIndice();
        $this->artisan('scout:sync-index-settings')->assertSuccessful();
        $this->esperarIndice();

        Storage::fake('originales');
        $this->seed([RolesSeeder::class, ReglasNoTramiteSeeder::class]);
        $this->administrativo = User::factory()->create()->assignRole('administrativo');

        $ingesta = app(IngestaCorreoService::class);
        foreach (['oficio-con-pdf', 'reenvio', 'sin-message-id'] as $correo) {
            $ingesta->procesar(File::get(base_path("tests/fixtures/correos/{$correo}.eml")));
        }
        $this->esperarIndice();
    }

    private function esperarIndice(): void
    {
        $pendientes = $this->meili->getTasks((new TasksQuery)->setStatuses(['enqueued', 'processing']));
        foreach ($pendientes->getResults() as $tarea) {
            $this->meili->waitForTask($tarea['uid'], 15000);
        }
    }

    /** @return list<string> */
    private function buscar(User $user, string $texto, ?int &$total = null): array
    {
        $asuntos = [];
        $this->actingAs($user)->get('/expedientes?q='.urlencode($texto))->assertInertia(function (AssertableInertia $p) use (&$asuntos, &$total) {
            $asuntos = collect($p->toArray()['props']['expedientes']['data'])->pluck('asunto')->all();
            // El total lo informa Meilisearch: prueba su filtro, no la segunda barrera en la base.
            $total = $p->toArray()['props']['expedientes']['meta']['total'];
        });

        return $asuntos;
    }

    public function test_encuentra_por_texto_del_pdf_remitente_y_con_errores_de_tipeo(): void
    {
        $oficio = 'OFICIO N° 123-2026-MDE: Invitación a ceremonia de aniversario';

        $this->assertSame([$oficio], $this->buscar($this->administrativo, 'aniversario institucional'));
        $this->assertSame([$oficio], $this->buscar($this->administrativo, 'ceremnia'));
        $this->assertSame(['RV: Solicitud de docentes para campaña de salud'], $this->buscar($this->administrativo, 'redsalud'));
    }

    public function test_el_coordinador_solo_obtiene_resultados_de_sus_areas(): void
    {
        $escuela = Area::factory()->create();
        $coordinador = User::factory()->create()->assignRole('coordinador');
        AreaResponsable::create(['area_id' => $escuela->id, 'user_id' => $coordinador->id, 'tipo' => 'titular', 'vigente_desde' => today()]);

        $oficio = Expediente::where('asunto', 'like', 'OFICIO%')->sole();
        $oficio->update(['area_principal_id' => $escuela->id]);
        $this->esperarIndice();

        $this->assertSame([$oficio->asunto], $this->buscar($coordinador, 'ceremonia', $total));
        $this->assertSame(1, $total);
        $this->assertSame([], $this->buscar($coordinador, 'docentes', $total));
        $this->assertSame(0, $total);
        $this->assertCount(1, $this->buscar($this->administrativo, 'docentes'));
    }

    public function test_responde_en_menos_de_un_segundo(): void
    {
        $this->buscar($this->administrativo, 'ceremonia');

        $inicio = microtime(true);
        $this->buscar($this->administrativo, 'presentación estudiantes');
        $this->assertLessThan(1.0, microtime(true) - $inicio);
    }
}
