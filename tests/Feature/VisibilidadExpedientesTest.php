<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\Expediente;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisibilidadExpedientesTest extends TestCase
{
    use RefreshDatabase;

    private Area $escuela;

    private Area $laboratorio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->escuela = Area::factory()->create();
        $this->laboratorio = Area::factory()->create();
    }

    private function usuario(string $rol): User
    {
        return User::factory()->create()->assignRole($rol);
    }

    /** @return list<int> */
    private function visibles(User $user): array
    {
        return Expediente::visiblesPara($user)->orderBy('id')->pluck('id')->all();
    }

    public function test_director_y_administrativo_ven_todo(): void
    {
        $ids = Expediente::factory(3)->create()->pluck('id')->all();

        $this->assertSame($ids, $this->visibles($this->usuario('director')));
        $this->assertSame($ids, $this->visibles($this->usuario('administrativo')));
    }

    public function test_el_superadmin_no_accede_al_contenido(): void
    {
        $superadmin = $this->usuario('superadmin');
        $expediente = Expediente::factory()->create();

        $this->assertSame([], $this->visibles($superadmin));
        $this->assertFalse($superadmin->can('viewAny', Expediente::class));
        $this->assertFalse($superadmin->can('view', $expediente));
    }

    public function test_el_coordinador_solo_ve_sus_areas_vigentes(): void
    {
        $coordinador = $this->usuario('coordinador');
        AreaResponsable::create(['area_id' => $this->escuela->id, 'user_id' => $coordinador->id, 'tipo' => 'titular', 'vigente_desde' => today()->subYear()]);
        AreaResponsable::create(['area_id' => $this->laboratorio->id, 'user_id' => $coordinador->id, 'tipo' => 'suplente',
            'vigente_desde' => today()->subMonths(2), 'vigente_hasta' => today()->subMonth()]);

        $suyo = Expediente::factory()->create(['area_principal_id' => $this->escuela->id]);
        $vencido = Expediente::factory()->create(['area_principal_id' => $this->laboratorio->id]);
        Expediente::factory()->create();

        $this->assertSame([$suyo->id], $this->visibles($coordinador));
        $this->assertTrue($coordinador->can('view', $suyo));
        $this->assertFalse($coordinador->can('view', $vencido));
        $this->assertFalse($coordinador->can('registrar', $suyo));
    }

    public function test_otros_solo_ven_lo_asignado_por_expediente(): void
    {
        $otro = $this->usuario('otros');
        $asignado = Expediente::factory()->create(['responsable_id' => $otro->id]);
        Expediente::factory()->create();

        $this->assertSame([$asignado->id], $this->visibles($otro));
        $this->assertFalse($otro->can('viewAny', Expediente::class));
    }
}
