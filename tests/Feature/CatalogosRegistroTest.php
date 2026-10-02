<?php

namespace Tests\Feature;

use App\Correo\MensajeLeido;
use App\Enums\EstadoExpediente;
use App\Models\Emisor;
use App\Models\Expediente;
use App\Models\ReglaNoTramite;
use App\Models\TipoDocumento;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogosRegistroTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $administrativo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->admin = User::factory()->create()->assignRole('superadmin');
        $this->administrativo = User::factory()->create()->assignRole('administrativo');
    }

    public function test_un_emisor_con_el_mismo_nombre_normalizado_se_bloquea(): void
    {
        $this->actingAs($this->admin)->post('/emisores', ['nombre' => 'Oficina de Gestión Académica', 'tipo' => 'interno'])->assertRedirect('/emisores');

        $this->actingAs($this->admin)->post('/emisores', ['nombre' => 'oficina de gestion  academica.', 'tipo' => 'interno'])
            ->assertSessionHasErrors(['nombre' => 'Ya existe «Oficina de Gestión Académica».']);
        $this->assertSame(1, Emisor::count());
        $this->assertDatabaseHas('auditoria', ['accion' => 'emisor.creado']);
    }

    public function test_el_alta_en_linea_avisa_de_parecidos_hasta_confirmar(): void
    {
        Emisor::create(['nombre' => 'Municipalidad Provincial de La Convención', 'tipo' => 'externo']);
        $datos = ['nombre' => 'Municipalidad Provincial La Convencion', 'tipo' => 'externo'];

        $this->actingAs($this->administrativo)->postJson('/emisores/rapido', $datos)
            ->assertStatus(422)->assertJsonPath('errors.nombre.0', fn (string $m) => str_starts_with($m, 'Parecido a «Municipalidad Provincial de La Convención»'));

        $this->actingAs($this->administrativo)->postJson('/emisores/rapido', $datos + ['confirmado' => true])
            ->assertCreated()->assertJsonPath('label', 'Municipalidad Provincial La Convencion');
    }

    public function test_los_parecidos_se_proponen_y_fusionar_reasigna_expedientes(): void
    {
        $bueno = Emisor::create(['nombre' => 'Dirección General de Administración', 'tipo' => 'interno']);
        $duplicado = Emisor::create(['nombre' => 'Direccion Gral de Administracion', 'tipo' => 'interno']);
        Emisor::create(['nombre' => 'Colegio San Martín', 'tipo' => 'externo']);
        $expedientes = Expediente::factory()->count(2)->create(['emisor_id' => $duplicado->id]);

        $this->assertEquals([['a' => ['id' => $bueno->id, 'nombre' => $bueno->nombre], 'b' => ['id' => $duplicado->id, 'nombre' => $duplicado->nombre]]], Emisor::posiblesDuplicados());

        $this->actingAs($this->admin)->post("/emisores/{$duplicado->id}/fusionar", ['destino_id' => $bueno->id])
            ->assertInertiaFlash('toast.mensaje', "«{$duplicado->nombre}» se fusionó en «{$bueno->nombre}»: 2 expedientes reasignados.");

        $this->assertSame([$bueno->id, $bueno->id], Expediente::whereKey($expedientes->pluck('id'))->pluck('emisor_id')->all());
        $this->assertSame($bueno->id, $duplicado->fresh()->fusionado_en_id);
        $this->assertSame([], Emisor::posiblesDuplicados());
        // El nombre del fusionado queda libre: el índice único ignora a los fusionados.
        $this->assertNull(Emisor::mismoNombre($duplicado->nombre));
        $this->assertDatabaseHas('auditoria', ['accion' => 'emisor.fusionado']);
    }

    public function test_registrar_guarda_emisor_y_tipo_de_documento(): void
    {
        $emisor = Emisor::create(['nombre' => 'Rectorado', 'tipo' => 'interno']);
        $tipo = TipoDocumento::create(['nombre' => 'Oficio múltiple']);
        $inactivo = TipoDocumento::create(['nombre' => 'Memorando', 'activo' => false]);
        $expediente = Expediente::factory()->create();

        $this->actingAs($this->administrativo)->post("/expedientes/{$expediente->id}/confirmar", ['tipo_documento_id' => $inactivo->id])
            ->assertSessionHasErrors('tipo_documento_id');
        $this->actingAs($this->administrativo)->post("/expedientes/{$expediente->id}/confirmar", ['emisor_id' => $emisor->id, 'tipo_documento_id' => $tipo->id])
            ->assertSessionHasNoErrors();

        $expediente->refresh();
        $this->assertSame([EstadoExpediente::Registrado, $emisor->id, $tipo->id], [$expediente->estado, $expediente->emisor_id, $expediente->tipo_documento_id]);
        $registro = DB::table('auditoria')->where('accion', 'registro.asignado')->first();
        $this->assertSame($emisor->id, json_decode($registro->valor_nuevo, true)['emisor_id']);
    }

    public function test_una_regla_no_tramite_creada_desde_el_panel_aplica_al_siguiente_correo(): void
    {
        $this->actingAs($this->admin)->post('/reglas-no-tramite', ['nombre' => 'Boletín del banco', 'campo' => 'dominio', 'valor' => ' Banco.PE ', 'activa' => true])
            ->assertRedirect('/reglas-no-tramite');

        $regla = ReglaNoTramite::sole();
        $this->assertSame('banco.pe', $regla->valor);
        $this->assertTrue($regla->is(ReglaNoTramite::primeraQueAplica($this->mensaje('avisos@correo.banco.pe'))));
        $this->assertNull(ReglaNoTramite::primeraQueAplica($this->mensaje('ana@otro.pe')));
    }

    public function test_tipos_de_documento_e_instrucciones_se_crean_y_quedan_auditados(): void
    {
        $this->actingAs($this->admin)->post('/tipos-documento', ['nombre' => 'Oficio circular', 'activo' => true])->assertRedirect('/tipos-documento');
        $this->actingAs($this->admin)->post('/tipos-documento', ['nombre' => 'Oficio circular', 'activo' => true])->assertSessionHasErrors('nombre');
        $this->actingAs($this->admin)->post('/instrucciones', ['texto' => 'Para conocimiento', 'orden' => 1, 'activa' => true])->assertRedirect('/instrucciones');

        $this->assertDatabaseHas('auditoria', ['accion' => 'tipo_documento.creado']);
        $this->assertDatabaseHas('auditoria', ['accion' => 'instruccion.creada']);
    }

    public function test_quien_registra_da_de_alta_emisores_en_linea_pero_no_administra_catalogos(): void
    {
        $this->actingAs($this->administrativo)->get('/emisores')->assertForbidden();
        $this->actingAs($this->administrativo)->post('/tipos-documento', ['nombre' => 'Carta', 'activo' => true])->assertForbidden();
        $this->actingAs($this->administrativo)->get('/reglas-no-tramite')->assertForbidden();
        $this->actingAs(User::factory()->create()->assignRole('director'))->postJson('/emisores/rapido', ['nombre' => 'X', 'tipo' => 'externo'])->assertForbidden();
    }

    private function mensaje(string $de): MensajeLeido
    {
        return new MensajeLeido(
            messageId: '<x@test>', enRespuestaA: [], deEmail: $de, deNombre: null, para: [], cc: [],
            asunto: 'Boletín', fecha: now()->toImmutable(), cuerpo: '', encabezados: [], adjuntos: [],
        );
    }
}
