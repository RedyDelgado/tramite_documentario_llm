<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class AreaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->admin = User::factory()->create()->assignRole('superadmin');
    }

    private function datos(array $cambios = []): array
    {
        return [
            'nombre' => 'Mesa de partes',
            'descripcion' => 'Recepción de documentos.',
            'palabras_clave' => ['oficio', 'solicitud'],
            'parent_id' => null,
            'orden' => 1,
            'activa' => true,
            ...$cambios,
        ];
    }

    public function test_sin_permiso_de_configuracion_no_gestiona_areas(): void
    {
        $director = User::factory()->create()->assignRole('director');

        $this->actingAs($director)->get('/areas')->assertForbidden();
        $this->actingAs($director)->post('/areas', $this->datos())->assertForbidden();
        $this->assertDatabaseCount('areas', 0);
    }

    public function test_el_permiso_delegado_basta_sin_ser_superadmin(): void
    {
        $administrativo = User::factory()->create()->assignRole('administrativo');
        $administrativo->givePermissionTo('configuracion.gestionar');

        $this->actingAs($administrativo)->get('/areas')->assertOk();
    }

    public function test_la_lista_va_completa_para_buscar_y_paginar_en_el_navegador(): void
    {
        Area::factory()->count(12)->create();
        Area::factory()->create(['nombre' => 'Archivo', 'activa' => false]);

        // Activas e inactivas, sin paginar: el filtro y las páginas de 10 los hace useListaLocal.
        $this->actingAs($this->admin)->get('/areas?q=mesa')->assertInertia(fn (AssertableInertia $p) => $p
            ->component('areas/Index')
            ->has('areas', 13)
            ->missing('filtros'));
    }

    public function test_crea_un_area_con_sus_palabras_clave(): void
    {
        $this->actingAs($this->admin)->post('/areas', $this->datos())->assertRedirect('/areas');

        $area = Area::sole();
        $this->assertSame('Mesa de partes', $area->nombre);
        $this->assertSame(['oficio', 'solicitud'], $area->palabras_clave);
    }

    public function test_avisa_al_crear(): void
    {
        $this->actingAs($this->admin)
            ->followingRedirects()
            ->post('/areas', $this->datos())
            ->assertInertia(fn (AssertableInertia $p) => $p->component('areas/Index')->hasFlash('toast.tipo', 'ok'));
    }

    public function test_nombre_obligatorio_y_unico(): void
    {
        Area::factory()->create(['nombre' => 'Mesa de partes']);

        $this->actingAs($this->admin)->post('/areas', $this->datos(['nombre' => '']))
            ->assertSessionHasErrors(['nombre' => 'El campo nombre es obligatorio.']);
        $this->actingAs($this->admin)->post('/areas', $this->datos())
            ->assertSessionHasErrors(['nombre' => 'Ya existe un registro con ese nombre.']);
    }

    public function test_la_jerarquia_no_admite_ciclos(): void
    {
        $escuela = Area::factory()->create();
        $laboratorio = Area::factory()->create(['parent_id' => $escuela->id]);
        $equipo = Area::factory()->create(['parent_id' => $laboratorio->id]);

        $this->actingAs($this->admin)
            ->put("/areas/{$escuela->id}", $this->datos(['nombre' => $escuela->nombre, 'parent_id' => $equipo->id]))
            ->assertSessionHasErrors('parent_id');

        $this->actingAs($this->admin)
            ->put("/areas/{$escuela->id}", $this->datos(['nombre' => $escuela->nombre, 'parent_id' => $escuela->id]))
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($escuela->fresh()->parent_id);
    }

    public function test_desactivar_no_borra_el_area(): void
    {
        $area = Area::factory()->create();

        $this->actingAs($this->admin)->patch("/areas/{$area->id}/estado", ['activa' => false])->assertRedirect();

        $this->assertFalse($area->fresh()->activa);
        $this->assertNotSoftDeleted($area);
    }

    public function test_cambiar_un_area_no_toca_las_demas(): void
    {
        $area = Area::factory()->create(['nombre' => 'Mesa de partes']);
        $otra = Area::factory()->create(['nombre' => 'Dirección', 'orden' => 5]);

        $this->actingAs($this->admin)
            ->put("/areas/{$area->id}", $this->datos(['nombre' => 'Mesa de partes central', 'orden' => 2]))
            ->assertRedirect('/areas');

        $this->assertSame('Mesa de partes central', $area->fresh()->nombre);
        $this->assertSame(5, $otra->fresh()->orden);
    }
}
