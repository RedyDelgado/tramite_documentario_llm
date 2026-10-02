<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Enums\OrigenExpediente;
use App\Models\Area;
use App\Models\Emisor;
use App\Models\Expediente;
use App\Models\Movimiento;
use App\Models\TipoTramite;
use App\Models\UbicacionFisica;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OriginalFisicoTest extends TestCase
{
    use RefreshDatabase;

    private User $administrativo;

    private Expediente $expediente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        Storage::fake('originales');
        Queue::fake();
        $this->travelTo('2026-08-17 10:30:00');
        $this->administrativo = User::factory()->create(['name' => 'Ana Quispe'])->assignRole('administrativo');
        $this->expediente = Expediente::factory()->create([
            'origen' => OrigenExpediente::Fisico, 'estado' => EstadoExpediente::Registrado, 'anio' => 2026, 'secuencia' => 38,
            'fecha_ingreso' => now(), 'registrado_por' => $this->administrativo->id, 'custodio_id' => $this->administrativo->id,
            'emisor_id' => Emisor::create(['nombre' => 'Municipalidad Provincial', 'tipo' => 'externo'])->id,
            'numero_documento_original' => 'OFICIO N° 120-2026-MPLC', 'folios' => 12, 'asunto' => 'Invitación al desfile cívico',
        ]);
    }

    public function test_la_constancia_se_imprime_con_qr_y_datos_correctos_y_queda_auditada(): void
    {
        $this->actingAs($this->administrativo)->get("/expedientes/{$this->expediente->id}/constancia")
            ->assertOk()
            ->assertSee('Constancia de recepción')
            ->assertSee('N°00038')
            ->assertSee('REG-2026-00038')
            ->assertSee('17/08/2026 10:30')
            ->assertSee('Municipalidad Provincial')
            ->assertSee('OFICIO N° 120-2026-MPLC')
            ->assertSee('Invitación al desfile cívico')
            ->assertSee('Ana Quispe')
            ->assertSee('<svg', false);

        $this->assertDatabaseHas('auditoria', ['accion' => 'constancia.impresa', 'entidad_id' => (string) $this->expediente->id]);
    }

    public function test_el_qr_y_la_busqueda_por_codigo_abren_el_expediente(): void
    {
        $this->actingAs($this->administrativo)->get('/qr/REG-2026-00038')->assertRedirect("/expedientes/{$this->expediente->id}");
        $this->actingAs($this->administrativo)->get('/expedientes?q='.urlencode('http://localhost:8100/qr/REG-2026-00038'))
            ->assertRedirect("/expedientes/{$this->expediente->id}");
        $this->actingAs($this->administrativo)->get('/qr/REG-2026-99999')->assertNotFound();

        // Un coordinador de otra área llega al expediente por el QR, pero su vista se lo niega.
        $ajeno = User::factory()->create()->assignRole('coordinador');
        $this->actingAs($ajeno)->get('/qr/REG-2026-00038')->assertRedirect();
        $this->actingAs($ajeno)->get("/expedientes/{$this->expediente->id}")->assertForbidden();
    }

    public function test_mover_el_original_queda_auditado_y_nunca_hay_forma_de_descartarlo(): void
    {
        $caja = UbicacionFisica::create(['nombre' => 'Caja 3 – 2026']);
        $custodio = User::factory()->create();

        $this->actingAs($this->administrativo)->post("/expedientes/{$this->expediente->id}/original", [
            'ubicacion_fisica_id' => $caja->id, 'custodio_id' => $custodio->id, 'nota' => 'Archivado tras derivar',
        ])->assertSessionHasNoErrors();

        $this->assertSame([$caja->id, $custodio->id], [$this->expediente->fresh()->ubicacion_fisica_id, $this->expediente->fresh()->custodio_id]);
        $this->assertSame('original_movido', Movimiento::sole()->tipo);
        $auditoria = DB::table('auditoria')->where('accion', 'original.movido')->sole();
        $this->assertSame($this->administrativo->id, json_decode($auditoria->valor_anterior, true)['custodio_id']);

        $this->assertEmpty(collect(Route::getRoutes()->getRoutesByName())->keys()->filter(fn ($n) => str_contains($n, 'descart') || str_contains($n, 'destruir')));
        $this->assertFalse(Route::has('documentos.destroy'));
    }

    public function test_un_expediente_de_correo_no_tiene_original_fisico(): void
    {
        $correo = Expediente::factory()->create(['estado' => EstadoExpediente::Registrado, 'anio' => 2026, 'secuencia' => 39]);

        $this->actingAs($this->administrativo)->post("/expedientes/{$correo->id}/original", ['custodio_id' => $this->administrativo->id])
            ->assertInertiaFlash('toast.tipo', 'error');
    }

    public function test_el_cargo_de_entrega_se_imprime_y_el_firmado_se_adjunta_a_su_derivacion(): void
    {
        $area = Area::factory()->create(['nombre' => 'Escuela de Educación']);
        $this->actingAs($this->administrativo)->post("/expedientes/{$this->expediente->id}/derivar", [
            'tipo_tramite_id' => TipoTramite::create(['nombre' => 'Invitación', 'plazo_dias' => 3])->id,
            'area_id' => $area->id, 'requiere_respuesta' => false, 'instruccion' => 'Para conocimiento',
        ])->assertSessionHasNoErrors();
        $derivacion = Movimiento::where('tipo', 'derivacion')->sole();

        $this->actingAs($this->administrativo)->get("/movimientos/{$derivacion->id}/cargo")
            ->assertOk()->assertSee('Cargo de entrega')->assertSee('Escuela de Educación')->assertSee('Para conocimiento');
        $this->assertDatabaseHas('auditoria', ['accion' => 'cargo.impreso']);

        $this->actingAs($this->administrativo)->post("/movimientos/{$derivacion->id}/cargo", [
            'archivo' => UploadedFile::fake()->createWithContent('cargo.pdf', RegistroFisicoTest::pdf(['Recibido 18/08/2026'])),
        ])->assertSessionHasNoErrors();

        $this->assertSame($derivacion->id, $this->expediente->documentos()->sole()->movimiento_id);
        $this->actingAs($this->administrativo)->get("/expedientes/{$this->expediente->id}")
            ->assertInertia(fn ($page) => $page->where('expediente.cargos.0.firmado', $this->expediente->documentos()->sole()->id));
    }

    public function test_las_ubicaciones_se_administran_desde_el_panel(): void
    {
        $admin = User::factory()->create()->assignRole('superadmin');

        $this->actingAs($admin)->post('/ubicaciones', ['nombre' => 'Archivador 1', 'descripcion' => null, 'activa' => true])->assertRedirect('/ubicaciones');
        $this->actingAs($admin)->post('/ubicaciones', ['nombre' => 'Archivador 1', 'activa' => true])->assertSessionHasErrors('nombre');
        $this->actingAs($this->administrativo)->get('/ubicaciones')->assertForbidden();
        $this->assertDatabaseHas('auditoria', ['accion' => 'ubicacion.creada']);
    }

    public function test_solo_quien_registra_o_deriva_imprime_y_mueve_originales(): void
    {
        $coordinador = User::factory()->create()->assignRole('coordinador');
        $this->expediente->update(['responsable_id' => $coordinador->id]);

        $this->actingAs($coordinador)->get("/expedientes/{$this->expediente->id}/constancia")->assertForbidden();
        $this->actingAs($coordinador)->post("/expedientes/{$this->expediente->id}/original", ['custodio_id' => $coordinador->id])->assertForbidden();
    }
}
