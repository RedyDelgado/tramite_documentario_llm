<?php

namespace Tests\Feature;

use App\Models\Auditoria;
use App\Models\Conversacion;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class MensajeriaTest extends TestCase
{
    use RefreshDatabase;

    private User $ana;

    private User $beto;

    private User $carla;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->ana = User::factory()->create(['name' => 'Ana'])->assignRole('administrativo');
        $this->beto = User::factory()->create(['name' => 'Beto'])->assignRole('administrativo');
        $this->carla = User::factory()->create(['name' => 'Carla'])->assignRole('director');
    }

    private function abrir(User $quien, array $con, ?string $nombre = null): Conversacion
    {
        $this->actingAs($quien)->post('/mensajes', ['usuarios' => array_map(fn (User $u) => $u->id, $con), 'nombre' => $nombre])->assertRedirect();

        return Conversacion::latest('id')->firstOrFail();
    }

    public function test_entre_dos_personas_hay_una_sola_conversacion_la_empiece_quien_la_empiece(): void
    {
        $primera = $this->abrir($this->ana, [$this->beto]);
        $this->actingAs($this->beto)->post('/mensajes', ['usuarios' => [$this->ana->id]])->assertRedirect("/mensajes?c={$primera->id}");

        $this->assertDatabaseCount('conversaciones', 1);
        $this->assertFalse($primera->esGrupo());
    }

    public function test_con_varias_personas_crea_un_grupo_con_nombre(): void
    {
        $grupo = $this->abrir($this->ana, [$this->beto, $this->carla], 'Mesa de partes');

        $this->assertTrue($grupo->esGrupo());
        $this->assertSame('Mesa de partes', $grupo->nombre);
        $this->assertEqualsCanonicalizing([$this->ana->id, $this->beto->id, $this->carla->id], $grupo->participantes()->pluck('users.id')->all());
    }

    public function test_no_se_conversa_con_uno_mismo_ni_con_alguien_inactivo(): void
    {
        $inactivo = User::factory()->create(['activo' => false]);

        $this->actingAs($this->ana)->post('/mensajes', ['usuarios' => [$this->ana->id]]);
        $this->actingAs($this->ana)->post('/mensajes', ['usuarios' => [$inactivo->id]]);

        $this->assertDatabaseCount('conversaciones', 0);
    }

    public function test_quien_no_participa_no_lee_ni_escribe(): void
    {
        $conversacion = $this->abrir($this->ana, [$this->beto]);
        // Ni siquiera el superadmin lee conversaciones ajenas.
        $admin = User::factory()->create()->assignRole('superadmin');

        foreach ([$this->carla, $admin] as $ajeno) {
            $this->actingAs($ajeno)->get("/mensajes?c={$conversacion->id}")->assertForbidden();
            $this->actingAs($ajeno)->post("/mensajes/{$conversacion->id}", ['texto' => 'hola'])->assertForbidden();
        }
        $this->assertDatabaseCount('mensajes', 0);
    }

    public function test_un_mensaje_queda_sin_leer_hasta_que_el_otro_abre_la_conversacion(): void
    {
        $conversacion = $this->abrir($this->ana, [$this->beto]);
        $this->actingAs($this->ana)->post("/mensajes/{$conversacion->id}", ['texto' => "  ¿Llegó el oficio 47?\nGracias  "])->assertRedirect();

        $this->actingAs($this->ana)->getJson('/mensajes/sin-leer')->assertJson(['total' => 0]);
        $this->actingAs($this->beto)->getJson('/mensajes/sin-leer')->assertJson(['total' => 1]);
        $this->actingAs($this->beto)->get('/mensajes')->assertInertia(fn (AssertableInertia $p) => $p
            ->where('conversaciones.0.titulo', 'Ana')
            ->where('conversaciones.0.sin_leer', 1)
            ->where('conversaciones.0.ultimo_texto', "¿Llegó el oficio 47?\nGracias"));

        $this->actingAs($this->beto)->get("/mensajes?c={$conversacion->id}")->assertInertia(fn (AssertableInertia $p) => $p
            ->component('mensajes/Index')
            ->where('activa.titulo', 'Ana')
            ->has('mensajes', 1)
            ->where('mensajes.0.autor', 'Ana')
            ->where('mensajes.0.propio', false));
        $this->actingAs($this->beto)->getJson('/mensajes/sin-leer')->assertJson(['total' => 0]);
    }

    public function test_un_usuario_desactivado_ya_no_escribe(): void
    {
        $conversacion = $this->abrir($this->ana, [$this->beto]);
        $this->beto->update(['activo' => false]);

        // El sistema ya cierra la sesión de quien desactivan; lo que importa es que el mensaje no entre.
        $this->actingAs($this->beto)->post("/mensajes/{$conversacion->id}", ['texto' => 'hola']);
        $this->assertDatabaseCount('mensajes', 0);
    }

    public function test_la_auditoria_registra_el_envio_pero_no_el_texto(): void
    {
        $conversacion = $this->abrir($this->ana, [$this->beto]);
        $this->actingAs($this->ana)->post("/mensajes/{$conversacion->id}", ['texto' => 'DNI 12345678 del alumno']);

        $evento = Auditoria::where('accion', 'mensaje.enviado')->sole();
        $this->assertSame($this->ana->id, $evento->usuario_id);
        $this->assertStringNotContainsString('12345678', json_encode($evento->valor_nuevo));
        $this->assertTrue(Auditoria::where('accion', 'conversacion.creada')->exists());
    }

    public function test_un_mensaje_vacio_no_se_envia(): void
    {
        $conversacion = $this->abrir($this->ana, [$this->beto]);

        $this->actingAs($this->ana)->post("/mensajes/{$conversacion->id}", ['texto' => '   '])->assertSessionHasErrors('texto');
        $this->assertDatabaseCount('mensajes', 0);
    }
}
