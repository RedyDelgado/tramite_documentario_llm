<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Mail\AvisoDerivacion;
use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\Expediente;
use App\Models\NotificacionEnviada;
use App\Models\TipoTramite;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** Un expediente derivado a varias áreas: cada una lo atiende con su plazo y queda atendido cuando lo atienden todas. */
class DerivacionVariasAreasTest extends TestCase
{
    use RefreshDatabase;

    private User $administrativo;

    private Area $sistemas;

    private Area $contabilidad;

    private Area $copia;

    private User $deSistemas;

    private User $deContabilidad;

    private User $deCopia;

    private TipoTramite $tipo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->travelTo('2026-10-05 09:00:00');
        $this->administrativo = User::factory()->create()->assignRole('administrativo');
        [$this->sistemas, $this->deSistemas] = $this->areaConCoordinador();
        [$this->contabilidad, $this->deContabilidad] = $this->areaConCoordinador();
        [$this->copia, $this->deCopia] = $this->areaConCoordinador();
        $this->tipo = TipoTramite::create(['nombre' => 'Requerimiento', 'plazo_dias' => 5, 'tipo_dias' => 'calendario']);
    }

    /** @return array{Area, User} */
    private function areaConCoordinador(): array
    {
        $area = Area::factory()->create();
        $user = User::factory()->create()->assignRole('coordinador');
        AreaResponsable::create(['area_id' => $area->id, 'user_id' => $user->id, 'tipo' => 'titular', 'vigente_desde' => '2026-01-01']);

        return [$area, $user];
    }

    private function derivar(Expediente $expediente, array $areas, array $copias = []): TestResponse
    {
        return $this->actingAs($this->administrativo)->post("/expedientes/{$expediente->id}/derivar", [
            'tipo_tramite_id' => $this->tipo->id, 'requiere_respuesta' => true, 'areas' => $areas, 'areas_copia' => $copias,
        ]);
    }

    private function registrado(): Expediente
    {
        return Expediente::factory()->create(['estado' => EstadoExpediente::Registrado, 'fecha_ingreso' => now(), 'registrado_por' => $this->administrativo->id]);
    }

    public function test_cada_area_recibe_su_aviso_con_su_plazo_y_la_copia_el_suyo_para_conocimiento(): void
    {
        Mail::fake();
        $expediente = $this->registrado();

        $this->derivar($expediente, [
            ['area_id' => $this->sistemas->id],
            ['area_id' => $this->contabilidad->id, 'fecha_limite' => '2026-10-20'],
        ], [$this->copia->id])->assertSessionHasNoErrors()->assertInertiaFlash('toast.mensaje', "Derivado a {$this->sistemas->nombre} y 1 área más.");

        $expediente->refresh();
        $this->assertSame(EstadoExpediente::Derivado, $expediente->estado);
        $this->assertSame($this->sistemas->id, $expediente->area_principal_id);
        $this->assertSame(['2026-10-12', '2026-10-20'], $expediente->atenciones->map(fn ($a) => $a->fecha_limite->toDateString())->all());
        // El plazo de 5 días vence en sábado y pasa al lunes; la del expediente es la más próxima de lo pendiente.
        $this->assertSame('2026-10-12', $expediente->fecha_limite->toDateString());

        Mail::assertQueued(AvisoDerivacion::class, fn (AvisoDerivacion $m) => $m->hasTo($this->deSistemas->email) && $m->atencion?->area_id === $this->sistemas->id);
        Mail::assertQueued(AvisoDerivacion::class, fn (AvisoDerivacion $m) => $m->hasTo($this->deContabilidad->email) && $m->atencion?->fecha_limite->toDateString() === '2026-10-20');
        Mail::assertQueued(AvisoDerivacion::class, fn (AvisoDerivacion $m) => $m->hasTo($this->deCopia->email) && $m->atencion === null);
        Mail::assertQueuedCount(3);
        $this->assertSame(['copia', 'derivacion', 'derivacion'], NotificacionEnviada::orderBy('tipo')->pluck('tipo')->all());

        // Reasignar sin cambiar destinos ni copias no vuelve a avisar.
        $this->derivar($expediente, [['area_id' => $this->sistemas->id], ['area_id' => $this->contabilidad->id]], [$this->copia->id]);
        Mail::assertQueuedCount(3);
    }

    public function test_queda_atendido_solo_cuando_lo_atienden_todas_las_areas(): void
    {
        $expediente = $this->registrado();
        $this->derivar($expediente, [['area_id' => $this->sistemas->id], ['area_id' => $this->contabilidad->id]]);

        $this->actingAs($this->deSistemas)->post("/expedientes/{$expediente->id}/tomar")->assertSessionHasNoErrors();
        $this->assertSame(EstadoExpediente::EnAtencion, $expediente->fresh()->estado);
        $this->actingAs($this->deSistemas)->post("/expedientes/{$expediente->id}/atender", ['nota' => 'Se respondió con el Oficio 045'])
            ->assertInertiaFlash('toast.mensaje', 'Atendido por tu área; faltan otras áreas.');

        // Sistemas ya cumplió: no atiende lo de Contabilidad.
        $this->assertFalse($this->deSistemas->can('atender', $expediente->fresh()));
        $this->assertSame(EstadoExpediente::EnAtencion, $expediente->fresh()->estado);
        $this->assertNotNull($expediente->fresh()->semaforo);

        $this->actingAs($this->deContabilidad)->post("/expedientes/{$expediente->id}/atender", ['nota' => ''])->assertSessionHasErrors('nota');
        $this->actingAs($this->deContabilidad)->post("/expedientes/{$expediente->id}/atender", ['nota' => 'Informe enviado'])
            ->assertInertiaFlash('toast.mensaje', 'Atendido: ya lo atendieron todas las áreas.');

        $expediente->refresh();
        $this->assertSame(EstadoExpediente::Atendido, $expediente->estado);
        $this->assertNotNull($expediente->atendido_at);
        $this->assertNull($expediente->semaforo);
        $this->assertSame(['Se respondió con el Oficio 045', 'Informe enviado'], $expediente->atenciones->pluck('nota_atencion')->all());
    }

    public function test_al_reasignar_no_se_quita_un_area_que_ya_atendio(): void
    {
        $expediente = $this->registrado();
        $this->derivar($expediente, [['area_id' => $this->sistemas->id], ['area_id' => $this->contabilidad->id]]);
        $this->actingAs($this->deSistemas)->post("/expedientes/{$expediente->id}/atender", ['nota' => 'Listo']);

        $this->derivar($expediente, [['area_id' => $this->contabilidad->id]])->assertInertiaFlash('toast.tipo', 'error');
        $this->assertCount(2, $expediente->atenciones()->get());

        // Quitar la que falta sí: queda atendido por la que ya cumplió.
        $this->derivar($expediente, [['area_id' => $this->sistemas->id]])->assertSessionHasNoErrors();
        $this->assertSame(EstadoExpediente::Atendido, $expediente->fresh()->estado);
    }

    public function test_un_area_pendiente_sin_quien_la_atienda_pone_el_semaforo_en_rojo(): void
    {
        $sinNadie = Area::factory()->create();
        $expediente = $this->registrado();
        $this->derivar($expediente, [['area_id' => $this->sistemas->id], ['area_id' => $sinNadie->id]]);

        $this->assertSame('rojo', $expediente->fresh()->semaforo->value);
    }

    public function test_cada_area_ve_el_expediente_en_su_bandeja(): void
    {
        $expediente = $this->registrado();
        $this->derivar($expediente, [['area_id' => $this->sistemas->id], ['area_id' => $this->contabilidad->id]], [$this->copia->id]);

        foreach ([$this->deSistemas, $this->deContabilidad, $this->deCopia] as $user) {
            $this->assertTrue($user->can('view', $expediente));
        }
        $this->assertFalse($this->deCopia->can('atender', $expediente));
        $this->actingAs($this->deContabilidad)->get('/expedientes?vista=todos')->assertOk()
            ->assertInertia(fn ($p) => $p->where('expedientes.data.0.area', "{$this->sistemas->nombre} +1"));
    }
}
