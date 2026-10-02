<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Enums\Semaforo;
use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\Configuracion;
use App\Models\Expediente;
use App\Models\TipoTramite;
use App\Models\User;
use App\Services\AtencionService;
use App\Services\SemaforoService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SemaforoTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->area = Area::factory()->create();
        AreaResponsable::create(['area_id' => $this->area->id, 'user_id' => User::factory()->create()->id, 'tipo' => 'titular', 'vigente_desde' => '2026-01-01']);
        $this->actingAs(User::factory()->create()->assignRole('administrativo'));
    }

    /** Ingresa el lunes 5 de octubre de 2026 y se deriva ese día. */
    private function derivado(?int $plazo, bool $requiereRespuesta = true, ?Area $area = null): Expediente
    {
        $this->travelTo('2026-10-05 09:00:00');
        $expediente = Expediente::factory()->create(['estado' => EstadoExpediente::Registrado, 'fecha_ingreso' => now()]);
        $tipo = TipoTramite::create(['nombre' => 'Tipo '.uniqid(), 'plazo_dias' => $plazo, 'tipo_dias' => 'calendario']);

        return app(AtencionService::class)->derivar($expediente, [
            'tipo_tramite_id' => $tipo->id, 'area_id' => ($area ?? $this->area)->id, 'requiere_respuesta' => $requiereRespuesta,
        ]);
    }

    private function semaforoEl(string $fecha, Expediente $expediente): ?Semaforo
    {
        $this->travelTo($fecha.' 08:00:00');
        app(SemaforoService::class)->recalcularAbiertos();

        return $expediente->fresh()->semaforo;
    }

    public function test_cambia_a_amarillo_y_a_rojo_al_vencer_el_plazo(): void
    {
        // 10 días calendario: vence el jueves 15; amarillo cuando queda menos del 30 %. Sin el criterio de inactividad.
        Configuracion::create(['clave' => 'semaforo.dias_sin_movimiento', 'valor' => 60]);
        $expediente = $this->derivado(10);
        $this->assertSame('2026-10-15', $expediente->fecha_limite->toDateString());
        $this->assertSame(Semaforo::Verde, $expediente->semaforo);

        $this->assertSame(Semaforo::Verde, $this->semaforoEl('2026-10-12', $expediente));
        $this->assertSame(Semaforo::Amarillo, $this->semaforoEl('2026-10-13', $expediente));
        $this->assertSame(Semaforo::Amarillo, $this->semaforoEl('2026-10-15', $expediente));
        $this->assertSame(Semaforo::Rojo, $this->semaforoEl('2026-10-16', $expediente));
    }

    public function test_los_umbrales_del_panel_rigen_en_el_siguiente_calculo(): void
    {
        Configuracion::create(['clave' => 'semaforo.dias_sin_movimiento', 'valor' => 60]);
        $expediente = $this->derivado(10);
        $this->assertSame(Semaforo::Verde, $this->semaforoEl('2026-10-12', $expediente));
        Configuracion::create(['clave' => 'semaforo.porcentaje_amarillo', 'valor' => 60]);

        $this->assertSame(Semaforo::Amarillo, $this->semaforoEl('2026-10-12', $expediente));
    }

    public function test_dias_sin_movimiento_dan_amarillo_y_un_movimiento_lo_devuelve_a_verde(): void
    {
        $expediente = $this->derivado(60);

        $this->assertSame(Semaforo::Verde, $this->semaforoEl('2026-10-10', $expediente));
        $this->assertSame(Semaforo::Amarillo, $this->semaforoEl('2026-10-11', $expediente));

        app(AtencionService::class)->comentar($expediente, 'Se pidió información a la oficina.');
        $this->assertSame(Semaforo::Verde, $expediente->fresh()->semaforo);
    }

    public function test_sin_responsable_en_el_area_es_rojo(): void
    {
        $this->assertSame(Semaforo::Rojo, $this->derivado(10, area: Area::factory()->create())->semaforo);
    }

    public function test_sin_respuesta_requerida_ni_plazo_no_pasa_a_rojo(): void
    {
        $expediente = $this->derivado(null, requiereRespuesta: false, area: Area::factory()->create());

        $this->assertNotSame(Semaforo::Rojo, $expediente->semaforo);
        $this->assertNotSame(Semaforo::Rojo, $this->semaforoEl('2027-03-01', $expediente));
    }

    public function test_historico_no_tramite_y_anulado_no_tienen_semaforo(): void
    {
        foreach ([EstadoExpediente::Historico, EstadoExpediente::NoTramite, EstadoExpediente::Anulado] as $estado) {
            $this->assertNull(Expediente::factory()->create(['estado' => $estado])->semaforo, $estado->value);
        }
        $this->assertSame(Semaforo::Gris, Expediente::factory()->create()->semaforo);
    }
}
