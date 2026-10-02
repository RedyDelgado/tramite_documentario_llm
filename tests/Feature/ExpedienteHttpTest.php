<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\User;
use App\Services\IngestaCorreoService;
use Database\Seeders\ReglasNoTramiteSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ExpedienteHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $administrativo;

    private Expediente $oficio;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('originales');
        $this->seed([RolesSeeder::class, ReglasNoTramiteSeeder::class]);
        $this->travelTo(now()->setDate(2026, 10, 5));
        $this->administrativo = User::factory()->create()->assignRole('administrativo');
        $this->oficio = app(IngestaCorreoService::class)->procesar(File::get(base_path('tests/fixtures/correos/oficio-con-pdf.eml')));
    }

    private function coordinadorDe(Area $area): User
    {
        $coordinador = User::factory()->create()->assignRole('coordinador');
        AreaResponsable::create(['area_id' => $area->id, 'user_id' => $coordinador->id, 'tipo' => 'titular', 'vigente_desde' => today()]);

        return $coordinador;
    }

    public function test_la_bandeja_lista_lo_visible_y_niega_al_superadmin(): void
    {
        $this->actingAs($this->administrativo)->get('/expedientes')->assertInertia(fn (AssertableInertia $p) => $p
            ->component('expedientes/Index')
            ->has('expedientes.data', 1)
            ->where('expedientes.data.0.estado.valor', 'por_revisar')
            ->where('expedientes.data.0.documentos_count', 1)
            ->where('expedientes.data.0.puede_registrar', true));

        $superadmin = User::factory()->create()->assignRole('superadmin');
        $this->actingAs($superadmin)->get('/expedientes')->assertForbidden();
        $this->actingAs($superadmin)->get("/expedientes/{$this->oficio->id}")->assertForbidden();
    }

    public function test_el_coordinador_no_abre_expedientes_de_otra_area(): void
    {
        $coordinador = $this->coordinadorDe(Area::factory()->create());

        $this->actingAs($coordinador)->get('/expedientes')->assertInertia(fn (AssertableInertia $p) => $p->has('expedientes.data', 0));
        $this->actingAs($coordinador)->get("/expedientes/{$this->oficio->id}")->assertForbidden();
        $this->actingAs($coordinador)->get('/documentos/'.Documento::sole()->id.'/descargar')->assertForbidden();
    }

    public function test_el_detalle_muestra_correos_y_documentos_y_queda_auditado(): void
    {
        $this->actingAs($this->administrativo)->get("/expedientes/{$this->oficio->id}")->assertInertia(fn (AssertableInertia $p) => $p
            ->component('expedientes/Index')
            ->has('expediente.correos', 1)
            ->where('expediente.correos.0.de_email', 'mesadepartes@munidemo.example')
            ->has('expediente.documentos', 1)
            ->where('expediente.documentos.0.con_texto', true)
            ->where('historial.0.accion', 'Ingresó al sistema'));

        $this->assertSame($this->administrativo->id, Auditoria::where('accion', 'expediente.consultado')->sole()->usuario_id);
    }

    public function test_registrar_por_http_asigna_numero_y_avisa(): void
    {
        $this->actingAs($this->administrativo)
            ->from("/expedientes/{$this->oficio->id}")
            ->followingRedirects()
            ->post("/expedientes/{$this->oficio->id}/confirmar")
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('expediente.numero_registro', 'N°00038')
                ->hasFlash('toast.mensaje', 'Registrado como N°00038 (REG-2026-00038).'));
    }

    public function test_una_regla_de_negocio_vuelve_con_el_error_como_aviso(): void
    {
        $this->actingAs($this->administrativo)->post("/expedientes/{$this->oficio->id}/confirmar");

        $this->actingAs($this->administrativo)
            ->from('/expedientes')
            ->followingRedirects()
            ->post("/expedientes/{$this->oficio->id}/confirmar")
            ->assertInertia(fn (AssertableInertia $p) => $p->hasFlash('toast.tipo', 'error'));

        $this->assertSame(1, Expediente::whereNotNull('secuencia')->count());
    }

    public function test_solo_quien_registra_puede_cambiar_el_estado(): void
    {
        $director = User::factory()->create()->assignRole('director');

        $this->actingAs($director)->post("/expedientes/{$this->oficio->id}/confirmar")->assertForbidden();
        $this->actingAs($director)->post("/expedientes/{$this->oficio->id}/no-tramite")->assertForbidden();
        $this->assertSame(EstadoExpediente::PorRevisar, $this->oficio->fresh()->estado);
    }

    public function test_anular_exige_motivo(): void
    {
        $this->actingAs($this->administrativo)->post("/expedientes/{$this->oficio->id}/confirmar");

        $this->actingAs($this->administrativo)->post("/expedientes/{$this->oficio->id}/anular", ['motivo' => ''])->assertSessionHasErrors('motivo');
        $this->actingAs($this->administrativo)->post("/expedientes/{$this->oficio->id}/anular", ['motivo' => 'Ingresado por error.'])->assertRedirect();

        $this->assertSame(EstadoExpediente::Anulado, $this->oficio->fresh()->estado);
    }

    public function test_descargar_un_original_entrega_el_archivo_intacto_y_lo_audita(): void
    {
        $documento = Documento::sole();

        $respuesta = $this->actingAs($this->administrativo)->get("/documentos/{$documento->id}/descargar");

        $respuesta->assertOk()->assertDownload('Oficio 123-2026-MDE.pdf');
        $this->assertSame($documento->sha256, hash('sha256', $respuesta->streamedContent()));
        $this->assertSame($documento->sha256, Auditoria::where('accion', 'documento.descargado')->sole()->valor_nuevo['sha256']);
    }

    public function test_la_busqueda_encuentra_por_el_texto_del_pdf(): void
    {
        config(['scout.driver' => 'collection']);

        $this->actingAs($this->administrativo)->get('/expedientes?q=ceremonia')->assertInertia(fn (AssertableInertia $p) => $p
            ->has('expedientes.data', 1)
            ->where('filtros.q', 'ceremonia'));
        $this->actingAs($this->administrativo)->get('/expedientes?q=inexistente')->assertInertia(fn (AssertableInertia $p) => $p->has('expedientes.data', 0));
    }
}
