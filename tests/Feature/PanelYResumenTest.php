<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Mail\ResumenDiario;
use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\Expediente;
use App\Models\User;
use App\Services\PanelService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PanelYResumenTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private User $coordinador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->travelTo('2026-10-20 09:00:00');
        $this->area = Area::factory()->create(['nombre' => 'Laboratorios']);
        $this->coordinador = User::factory()->create()->assignRole('coordinador');
        AreaResponsable::create(['area_id' => $this->area->id, 'user_id' => $this->coordinador->id, 'tipo' => 'titular', 'vigente_desde' => '2026-01-01']);
    }

    private function expediente(array $datos): Expediente
    {
        return Expediente::factory()->create($datos + ['area_principal_id' => $this->area->id, 'registrado_at' => '2026-10-01 09:00:00', 'fecha_ingreso' => '2026-10-01 09:00:00']);
    }

    public function test_los_indicadores_respetan_lo_que_cada_usuario_puede_ver(): void
    {
        $this->expediente(['estado' => EstadoExpediente::Derivado, 'fecha_limite' => '2026-10-10']);
        // Atendido el 5 con plazo al 10 (en plazo) y otro el 15 con plazo al 10 (fuera de plazo).
        $this->expediente(['estado' => EstadoExpediente::Cerrado, 'fecha_limite' => '2026-10-10', 'atendido_at' => '2026-10-05 10:00:00']);
        $this->expediente(['estado' => EstadoExpediente::Cerrado, 'fecha_limite' => '2026-10-10', 'atendido_at' => '2026-10-15 10:00:00']);
        Expediente::factory()->create(['estado' => EstadoExpediente::Derivado, 'area_principal_id' => Area::factory()->create()->id]);

        $delCoordinador = app(PanelService::class)->indicadores($this->coordinador);
        $delDirector = app(PanelService::class)->indicadores(User::factory()->create()->assignRole('director'));

        $this->assertSame(1, $delCoordinador['abiertos']);
        $this->assertSame(2, $delDirector['abiertos']);
        $this->assertSame(['atendidos' => 2, 'en_plazo' => 1], $delCoordinador['en_plazo']);
        $this->assertSame(1, $delCoordinador['por_semaforo']['rojo']);
        $this->assertSame([['area' => 'Laboratorios', 'responsable' => null, 'abiertos' => 1, 'rojos' => 1]], $delCoordinador['carga']);
        $this->assertSame([['nombre' => 'Laboratorios', 'dias' => 9.0, 'total' => 2]], $delCoordinador['tiempo_por_area']);
    }

    public function test_el_superadmin_no_recibe_indicadores_de_tramites(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('superadmin'))->get('/')
            ->assertInertia(fn ($page) => $page->component('Inicio')->where('indicadores', null));
    }

    public function test_el_resumen_diario_llega_solo_a_coordinadores_con_pendientes_y_queda_auditado(): void
    {
        Mail::fake();
        $vencido = $this->expediente(['estado' => EstadoExpediente::Derivado, 'fecha_limite' => '2026-10-10']);
        $this->expediente(['estado' => EstadoExpediente::Cerrado]);
        User::factory()->create()->assignRole('coordinador');

        $this->artisan('resumen:diario')->assertSuccessful();

        Mail::assertQueued(ResumenDiario::class, 1);
        Mail::assertQueued(ResumenDiario::class, function (ResumenDiario $mail) use ($vencido) {
            $mail->assertSeeInHtml(route('expedientes.show', $vencido));

            return $mail->hasTo($this->coordinador->email) && $mail->expedientes->pluck('id')->all() === [$vencido->id]
                && $mail->envelope()->subject === 'Trámites pendientes: 1 (1 en rojo)';
        });
        $this->assertDatabaseHas('auditoria', ['accion' => 'notificacion.resumen_diario']);
    }
}
