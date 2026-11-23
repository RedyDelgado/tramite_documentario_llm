<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\Auditoria;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\ExpedienteArea;
use App\Models\ReglaDerivacion;
use App\Models\TipoTramite;
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

    public function test_las_pestanas_separan_lo_que_espera_revision_de_lo_demas(): void
    {
        app(IngestaCorreoService::class)->procesar(File::get(base_path('tests/fixtures/correos/boletin.eml')));

        // Mesa de partes empieza por lo que espera revisión; cada pestaña trae su total.
        $this->actingAs($this->administrativo)->get('/expedientes')->assertInertia(fn (AssertableInertia $p) => $p
            ->where('vista', 'por_revisar')
            ->has('expedientes.data', 1)
            ->where('vistas.0', ['valor' => 'por_revisar', 'etiqueta' => 'Por revisar', 'ayuda' => 'Lo que llegó y espera que se decida si es un trámite.', 'total' => 1])
            ->where('vistas.3.etiqueta', 'No trámite')
            ->where('vistas.3.total', 1)
            // Cada pestaña explica su término a quien no lo conoce.
            ->where('vistas.3.ayuda', fn (string $ayuda) => str_contains($ayuda, 'No se borran'))
            ->where('vistas.5.total', 2));

        $this->get('/expedientes?vista=no_tramite')->assertInertia(fn (AssertableInertia $p) => $p
            ->has('expedientes.data', 1)
            ->where('expedientes.data.0.estado.valor', 'no_tramite'));

        // Quien no registra empieza por lo que está en trámite.
        $director = User::factory()->create()->assignRole('director');
        $this->actingAs($director)->get('/expedientes')->assertInertia(fn (AssertableInertia $p) => $p
            ->where('vista', 'en_curso')
            ->has('expedientes.data', 0));
    }

    public function test_la_lista_muestra_el_tipo_de_tramite_y_filtra_por_tipo_y_area(): void
    {
        [$academica, $direccion] = Area::factory()->count(2)->create();
        $nota = TipoTramite::create(['nombre' => 'Rectificación de nota', 'plazo_dias' => 5]);
        $constancia = TipoTramite::create(['nombre' => 'Constancia de estudios', 'plazo_dias' => 3]);
        $rectificacion = Expediente::factory()->create(['estado' => EstadoExpediente::Derivado, 'asunto' => 'Rectificación de nota de Cálculo', 'tipo_tramite_id' => $nota->id, 'area_principal_id' => $academica->id]);
        // Derivado a dos áreas: también cuenta para la segunda.
        ExpedienteArea::create(['expediente_id' => $rectificacion->id, 'area_id' => $direccion->id]);
        Expediente::factory()->create(['estado' => EstadoExpediente::Derivado, 'asunto' => 'Constancia para beca', 'tipo_tramite_id' => $constancia->id, 'area_principal_id' => $direccion->id]);

        $this->actingAs($this->administrativo)->get("/expedientes?tipo={$nota->id}")->assertInertia(fn (AssertableInertia $p) => $p
            ->where('vista', 'todos')
            ->has('expedientes.data', 1)
            ->where('expedientes.data.0.tipo_tramite', 'Rectificación de nota')
            ->has('tipos', 2)
            ->has('areas', 2));
        $this->get("/expedientes?area={$direccion->id}")->assertInertia(fn (AssertableInertia $p) => $p->has('expedientes.data', 2));
        $this->get("/expedientes?area={$academica->id}")->assertInertia(fn (AssertableInertia $p) => $p->has('expedientes.data', 1));
        // Sin derivar no hay tipo de trámite: la fila lo dice en vez de quedar en blanco.
        $this->get('/expedientes')->assertInertia(fn (AssertableInertia $p) => $p
            ->where('expedientes.data.0.id', $this->oficio->id)
            ->where('expedientes.data.0.tipo_tramite', null));

        config(['scout.driver' => 'collection']);
        $this->get("/expedientes?q=constancia&tipo={$nota->id}")->assertInertia(fn (AssertableInertia $p) => $p->has('expedientes.data', 0));
        $this->get("/expedientes?q=constancia&tipo={$constancia->id}")->assertInertia(fn (AssertableInertia $p) => $p->has('expedientes.data', 1));
    }

    public function test_en_tramite_lo_que_pide_accion_va_primero_y_por_revisar_empieza_por_lo_que_mas_espera(): void
    {
        $area = Area::factory()->create();
        $responsable = User::factory()->create();
        $crear = fn (string $asunto, array $datos) => Expediente::factory()->create(['asunto' => $asunto, 'area_principal_id' => $area->id, 'responsable_id' => $responsable->id, ...$datos]);
        // Creados en desorden a propósito: el orden lo pone la urgencia, no la llegada.
        $crear('verde', ['estado' => EstadoExpediente::Derivado, 'fecha_ingreso' => now(), 'fecha_limite' => '2026-10-30']);
        $crear('atendido', ['estado' => EstadoExpediente::Atendido, 'fecha_ingreso' => now()->subDays(20), 'atendido_at' => now()]);
        $crear('amarillo', ['estado' => EstadoExpediente::Derivado, 'fecha_ingreso' => now()->subDays(10), 'fecha_limite' => '2026-10-07']);
        $crear('rojo', ['estado' => EstadoExpediente::Derivado, 'fecha_ingreso' => now()->subDays(10), 'fecha_limite' => '2026-10-01']);
        Expediente::factory()->create(['asunto' => 'por derivar', 'estado' => EstadoExpediente::Registrado, 'fecha_ingreso' => now()]);

        $this->actingAs($this->administrativo)->get('/expedientes?vista=en_curso')->assertInertia(fn (AssertableInertia $p) => $p
            ->where('expedientes.data.0.asunto', 'por derivar')
            ->where('expedientes.data.1.semaforo', 'rojo')
            ->where('expedientes.data.2.semaforo', 'amarillo')
            ->where('expedientes.data.3.semaforo', 'verde')
            ->where('expedientes.data.4.asunto', 'atendido'));
        // Elegir la fecha de ingreso manda sobre la urgencia.
        $this->get('/expedientes?vista=en_curso&dir=desc')->assertInertia(fn (AssertableInertia $p) => $p->where('expedientes.data.4.asunto', 'atendido')
            ->where('expedientes.data.0.asunto', fn ($a) => in_array($a, ['verde', 'por derivar'], true)));

        $antiguo = Expediente::factory()->create(['asunto' => 'espera desde hace días', 'fecha_ingreso' => now()->subDays(4)]);
        $this->get('/expedientes?vista=por_revisar')->assertInertia(fn (AssertableInertia $p) => $p
            ->where('expedientes.data.0.id', fn ($id) => $this->oficio->fecha_ingreso->lt($antiguo->fecha_ingreso) ? $id === $this->oficio->id : $id === $antiguo->id));
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

    public function test_al_registrar_se_elige_el_tipo_de_tramite_con_la_sugerencia_de_la_regla(): void
    {
        $invitacion = TipoTramite::create(['nombre' => 'Invitación a evento', 'plazo_dias' => 3, 'tipo_dias' => 'habiles']);
        ReglaDerivacion::create(['nombre' => 'Invitaciones', 'tipo_tramite_id' => $invitacion->id, 'area_destino_id' => Area::factory()->create()->id, 'condicion' => ['palabras_clave' => ['invitación']], 'prioridad' => 1, 'activa' => true]);

        $this->actingAs($this->administrativo)->get("/expedientes/{$this->oficio->id}")->assertInertia(fn (AssertableInertia $p) => $p
            ->where('tipoTramiteSugerido', ['tipo_tramite_id' => $invitacion->id, 'regla' => 'Invitaciones'])
            ->has('opcionesTipoTramite', 1));

        $this->post("/expedientes/{$this->oficio->id}/confirmar", ['tipo_tramite_id' => $invitacion->id])->assertRedirect();

        $this->oficio->refresh();
        $this->assertSame($invitacion->id, $this->oficio->tipo_tramite_id);
        $this->assertSame(3, $this->oficio->plazo_dias_aplicado);
        $this->assertNotNull($this->oficio->fecha_limite);
        $this->get('/expedientes?vista=en_curso')->assertInertia(fn (AssertableInertia $p) => $p
            ->where('expedientes.data.0.tipo_tramite', 'Invitación a evento'));
    }

    public function test_registrar_sin_tipo_de_tramite_lo_deja_para_derivar(): void
    {
        $this->actingAs($this->administrativo)->post("/expedientes/{$this->oficio->id}/confirmar")->assertRedirect();

        $this->assertNull($this->oficio->refresh()->tipo_tramite_id);
        $this->assertNull($this->oficio->fecha_limite);
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
