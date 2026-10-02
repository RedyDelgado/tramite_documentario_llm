<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Cada rol ve y hace solo lo que debe (5), por rol y por área. */
class PermisosAtencionTest extends TestCase
{
    use RefreshDatabase;

    private Area $escuela;

    private Area $laboratorio;

    private Area $otraArea;

    private Expediente $expediente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->escuela = Area::factory()->create();
        $this->laboratorio = Area::factory()->create(['parent_id' => $this->escuela->id]);
        $this->otraArea = Area::factory()->create();
        $this->expediente = Expediente::factory()->create(['estado' => EstadoExpediente::Derivado, 'area_principal_id' => $this->laboratorio->id]);
    }

    private function coordinadorDe(Area $area): User
    {
        $user = User::factory()->create()->assignRole('coordinador');
        AreaResponsable::create(['area_id' => $area->id, 'user_id' => $user->id, 'tipo' => 'titular', 'vigente_desde' => '2026-01-01']);

        return $user;
    }

    private function rol(string $rol): User
    {
        return User::factory()->create()->assignRole($rol);
    }

    public function test_derivan_director_y_administrativo_y_nadie_mas(): void
    {
        $this->assertTrue($this->rol('director')->can('derivar', $this->expediente));
        $this->assertTrue($this->rol('administrativo')->can('derivar', $this->expediente));
        $this->assertFalse($this->coordinadorDe($this->laboratorio)->can('derivar', $this->expediente));
        $this->assertFalse($this->rol('otros')->can('derivar', $this->expediente));
        $this->assertFalse($this->rol('superadmin')->can('derivar', $this->expediente));
    }

    public function test_el_coordinador_de_escuela_ve_y_atiende_sus_areas_dependientes_y_no_otras(): void
    {
        $deEscuela = $this->coordinadorDe($this->escuela);
        $deOtra = $this->coordinadorDe($this->otraArea);

        $this->assertTrue($deEscuela->can('view', $this->expediente));
        $this->assertTrue($deEscuela->can('atender', $this->expediente));
        $this->assertFalse($deOtra->can('view', $this->expediente));
        $this->assertFalse($deOtra->can('atender', $this->expediente));
        $this->actingAs($deOtra)->post("/expedientes/{$this->expediente->id}/tomar")->assertForbidden();
    }

    public function test_otros_solo_ve_y_atiende_lo_asignado(): void
    {
        $asignado = $this->rol('otros');
        $ajeno = $this->rol('otros');
        $this->expediente->update(['responsable_id' => $asignado->id]);

        $this->assertTrue($asignado->can('view', $this->expediente));
        $this->assertTrue($asignado->can('atender', $this->expediente));
        $this->assertFalse($ajeno->can('view', $this->expediente));
        $this->actingAs($asignado)->post("/expedientes/{$this->expediente->id}/tomar")->assertSessionHasNoErrors();
        $this->assertSame(EstadoExpediente::EnAtencion, $this->expediente->fresh()->estado);
    }

    public function test_el_director_ve_todo_pero_no_atiende(): void
    {
        $director = $this->rol('director');

        $this->assertTrue($director->can('view', $this->expediente));
        $this->assertFalse($director->can('atender', $this->expediente));
    }

    public function test_el_superadmin_no_ve_ni_atiende_tramites(): void
    {
        $superadmin = $this->rol('superadmin');

        $this->assertFalse($superadmin->can('view', $this->expediente));
        $this->actingAs($superadmin)->get("/expedientes/{$this->expediente->id}")->assertForbidden();
        $this->actingAs($superadmin)->post("/expedientes/{$this->expediente->id}/comentar", ['nota' => 'x'])->assertForbidden();
    }
}
