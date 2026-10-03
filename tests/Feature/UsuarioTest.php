<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class UsuarioTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        config(['tramite.google_dominio' => 'uni.edu.pe']);
        $this->admin = User::factory()->create(['email' => 'admin@uni.edu.pe'])->assignRole('superadmin');
    }

    private function datos(array $cambios = []): array
    {
        return ['name' => 'Ana Quispe', 'email' => 'ana@uni.edu.pe', 'rol' => 'coordinador', 'activo' => true, ...$cambios];
    }

    public function test_delegar_la_configuracion_no_da_la_gestion_de_usuarios(): void
    {
        $administrativo = User::factory()->create()->assignRole('administrativo');
        $administrativo->givePermissionTo('configuracion.gestionar');

        $this->actingAs($administrativo)->get('/usuarios')->assertForbidden();
        $this->actingAs($administrativo)->post('/usuarios', $this->datos(['rol' => 'superadmin']))->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'ana@uni.edu.pe']);
    }

    public function test_el_superadmin_da_y_quita_la_configuracion_desde_la_ficha_y_queda_auditado(): void
    {
        $director = User::factory()->create(['email' => 'directora@uni.edu.pe'])->assignRole('director');
        $this->actingAs($director)->get('/areas')->assertForbidden();

        $this->actingAs($this->admin)->put("/usuarios/{$director->id}", $this->datos(['email' => 'directora@uni.edu.pe', 'rol' => 'director', 'administra_configuracion' => true]))
            ->assertRedirect('/usuarios');
        $this->actingAs($director->fresh())->get('/areas')->assertOk();
        // Sigue siendo director: ve los trámites igual que antes y no gestiona usuarios.
        $this->assertSame(['director'], $director->fresh()->getRoleNames()->all());
        $this->actingAs($director->fresh())->get('/usuarios')->assertForbidden();
        $this->actingAs($this->admin)->get('/usuarios')
            ->assertInertia(fn ($page) => $page->where('usuarios.data', fn ($u) => collect($u)->firstWhere('id', $director->id)['administra_configuracion'] === true));

        $this->actingAs($this->admin)->put("/usuarios/{$director->id}", $this->datos(['email' => 'directora@uni.edu.pe', 'rol' => 'director', 'administra_configuracion' => false]));
        $this->actingAs($director->fresh())->get('/areas')->assertForbidden();
        $this->assertSame(
            [['configuracion.gestionar' => true], ['configuracion.gestionar' => false]],
            DB::table('auditoria')->where('accion', 'usuario.permiso_cambiado')->orderBy('id')->pluck('valor_nuevo')->map(fn ($v) => json_decode($v, true))->all(),
        );
    }

    public function test_crea_un_usuario_con_su_rol_y_queda_auditado(): void
    {
        $this->actingAs($this->admin)->post('/usuarios', $this->datos(['email' => ' Ana@Uni.edu.pe ']))->assertRedirect('/usuarios');

        $ana = User::where('email', 'ana@uni.edu.pe')->firstOrFail();
        $this->assertTrue($ana->hasRole('coordinador'));
        $this->assertNull($ana->password);
        $this->assertDatabaseHas('auditoria', ['accion' => 'usuario.creado', 'entidad_id' => (string) $ana->id, 'usuario_id' => $this->admin->id]);
    }

    public function test_rechaza_correos_fuera_del_dominio_y_repetidos(): void
    {
        User::factory()->create(['email' => 'ana@uni.edu.pe']);

        $this->actingAs($this->admin)->post('/usuarios', $this->datos(['email' => 'ANA@uni.edu.pe']))->assertSessionHasErrors('email');
        $this->actingAs($this->admin)->post('/usuarios', $this->datos(['email' => 'ana@gmail.com']))->assertSessionHasErrors('email');
    }

    public function test_cambiar_el_rol_queda_auditado_con_antes_y_despues(): void
    {
        $ana = User::factory()->create(['email' => 'ana@uni.edu.pe'])->assignRole('coordinador');

        $this->actingAs($this->admin)->put("/usuarios/{$ana->id}", $this->datos(['rol' => 'director']))->assertRedirect('/usuarios');

        $this->assertSame(['director'], $ana->fresh()->getRoleNames()->all());
        $cambio = DB::table('auditoria')->where('accion', 'usuario.rol_cambiado')->first();
        $this->assertSame(['rol' => 'coordinador'], json_decode($cambio->valor_anterior, true));
        $this->assertSame(['rol' => 'director'], json_decode($cambio->valor_nuevo, true));
    }

    public function test_nadie_cambia_su_propio_rol_ni_se_desactiva(): void
    {
        $this->actingAs($this->admin)
            ->put("/usuarios/{$this->admin->id}", $this->datos(['email' => 'admin@uni.edu.pe', 'rol' => 'director']))
            ->assertInertiaFlash('toast.tipo', 'error');
        $this->actingAs($this->admin)->patch("/usuarios/{$this->admin->id}/estado", ['activo' => false])->assertInertiaFlash('toast.tipo', 'error');

        $this->assertTrue($this->admin->fresh()->hasRole('superadmin'));
        $this->assertTrue($this->admin->fresh()->activo);
    }

    public function test_desactivar_queda_auditado(): void
    {
        $ana = User::factory()->create()->assignRole('coordinador');

        $this->actingAs($this->admin)->patch("/usuarios/{$ana->id}/estado", ['activo' => false]);

        $this->assertFalse($ana->fresh()->activo);
        $this->assertDatabaseHas('auditoria', ['accion' => 'usuario.desactivado', 'entidad_id' => (string) $ana->id]);
    }

    public function test_lista_filtra_por_rol_estado_y_texto(): void
    {
        User::factory()->create(['name' => 'Ana Quispe'])->assignRole('coordinador');
        User::factory()->create(['name' => 'Luis Mamani', 'activo' => false])->assignRole('coordinador');
        User::factory()->create(['name' => 'Rosa Huamán'])->assignRole('director');

        $this->actingAs($this->admin)->get('/usuarios?rol=coordinador&estado=activos')
            ->assertInertia(fn (AssertableInertia $p) => $p->component('usuarios/Index')
                ->has('usuarios.data', 1)
                ->where('usuarios.data.0.name', 'Ana Quispe')
                ->where('usuarios.data.0.rol_etiqueta', 'Coordinador'));

        $this->actingAs($this->admin)->get('/usuarios?q=mamani')
            ->assertInertia(fn (AssertableInertia $p) => $p->has('usuarios.data', 1)->where('usuarios.data.0.name', 'Luis Mamani'));
    }
}
