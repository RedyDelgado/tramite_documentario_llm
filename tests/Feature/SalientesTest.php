<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\DocumentoSaliente;
use App\Models\Emisor;
use App\Models\Expediente;
use App\Models\PlantillaDocumento;
use App\Models\TipoDocumento;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

class SalientesTest extends TestCase
{
    use RefreshDatabase;

    private User $administrativo;

    private User $director;

    private Area $area;

    private TipoDocumento $oficio;

    private Expediente $expediente;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        Storage::fake('originales');
        $this->travelTo('2026-10-06 10:00:00');
        $this->administrativo = User::factory()->create()->assignRole('administrativo');
        $this->director = User::factory()->create()->assignRole('director');
        $this->area = Area::factory()->create(['nombre' => 'Dirección General de Administración', 'siglas' => 'DGA']);
        $this->oficio = TipoDocumento::create(['nombre' => 'Oficio']);
        $this->expediente = Expediente::factory()->create([
            'estado' => EstadoExpediente::EnAtencion, 'anio' => 2026, 'secuencia' => 38, 'asunto' => 'Requerimiento de información',
            'numero_documento_original' => 'OFICIO N° 120-2026-MPLC', 'remitente_email' => 'mesa@municipalidad.gob.pe',
            'emisor_id' => Emisor::create(['nombre' => 'Municipalidad Provincial', 'tipo' => 'externo'])->id,
        ]);
    }

    private function redactar(array $extra = [], ?User $autor = null): TestResponse
    {
        return $this->actingAs($autor ?? $this->administrativo)->post('/salientes', $extra + [
            'expediente_id' => $this->expediente->id, 'tipo_documento_id' => $this->oficio->id, 'area_id' => $this->area->id,
            'asunto' => 'Respuesta al requerimiento', 'cuerpo' => "Señor alcalde:\n\nRemitimos la información solicitada.",
            'destinatarios' => [['email' => 'mesa@municipalidad.gob.pe', 'nombre' => 'Mesa de partes']],
            'es_respuesta' => true, 'requiere_respuesta' => false, 'plazo_respuesta_dias' => null, 'esperar_firma' => false,
        ]);
    }

    public function test_lo_que_aprueba_el_coordinador_tambien_lo_aprueba_el_director_nunca_el_autor(): void
    {
        // Un área con un solo coordinador: si él redacta el informe, lo aprueba el director.
        $informe = TipoDocumento::create(['nombre' => 'Informe', 'aprueba_salida' => 'coordinador']);
        $coordinador = User::factory()->create()->assignRole('coordinador');
        AreaResponsable::create(['area_id' => $this->area->id, 'user_id' => $coordinador->id, 'tipo' => 'titular', 'vigente_desde' => '2026-01-01']);
        $this->redactar(['tipo_documento_id' => $informe->id, 'expediente_id' => null, 'es_respuesta' => false], $coordinador)->assertSessionHasNoErrors();
        $s = DocumentoSaliente::sole();
        $this->actingAs($coordinador)->post("/salientes/{$s->id}/revision")->assertSessionHasNoErrors();

        $this->actingAs($coordinador)->post("/salientes/{$s->id}/aprobar")->assertForbidden();
        $this->actingAs($this->director)->post("/salientes/{$s->id}/aprobar")->assertSessionHasNoErrors();
        $this->assertSame($this->director->id, $s->fresh()->aprobado_por);
    }

    public function test_ningun_documento_se_numera_sin_aprobacion_explicita_y_auditada(): void
    {
        $this->redactar()->assertSessionHasNoErrors();
        $s = DocumentoSaliente::sole();
        $this->assertSame(['borrador', null], [$s->estado, $s->numero]);

        // En borrador nadie lo aprueba; tampoco quien lo redactó una vez en revisión.
        $this->actingAs($this->director)->post("/salientes/{$s->id}/aprobar")->assertForbidden();
        $this->actingAs($this->administrativo)->post("/salientes/{$s->id}/revision")->assertSessionHasNoErrors();
        $this->actingAs($this->administrativo)->post("/salientes/{$s->id}/aprobar")->assertForbidden();

        $this->actingAs($this->director)->post("/salientes/{$s->id}/aprobar")->assertSessionHasNoErrors();

        $s->refresh();
        // Aprobado sale solo (envío automático, 7.3.4); en el test la cola es síncrona.
        $this->assertSame(['enviado', 'OFICIO N.º 001-2026-DGA', $this->director->id], [$s->estado, $s->numero, $s->aprobado_por]);
        $this->assertStringStartsWith('%PDF', Storage::disk('originales')->get($s->ruta_pdf));
        $this->assertSame($s->sha256_pdf, hash('sha256', Storage::disk('originales')->get($s->ruta_pdf)));
        $this->assertDatabaseHas('auditoria', ['accion' => 'saliente.aprobado', 'usuario_id' => $this->director->id]);
        // Lo aprobado no se edita.
        $this->actingAs($this->administrativo)->get("/salientes/{$s->id}/edit")->assertForbidden();
    }

    public function test_devolver_lo_regresa_a_borrador_con_la_observacion(): void
    {
        $this->redactar();
        $s = DocumentoSaliente::sole();
        $this->actingAs($this->administrativo)->post("/salientes/{$s->id}/revision");

        $this->actingAs($this->director)->post("/salientes/{$s->id}/devolver", ['observacion' => 'Falta el anexo'])->assertSessionHasNoErrors();

        $this->assertSame(['borrador', 'Falta el anexo', null], [$s->fresh()->estado, $s->fresh()->observacion, $s->fresh()->numero]);
    }

    public function test_si_el_tipo_lo_indica_aprueba_el_coordinador_del_area_que_emite(): void
    {
        $this->oficio->update(['aprueba_salida' => 'coordinador']);
        $coordinador = User::factory()->create()->assignRole('coordinador');
        AreaResponsable::create(['area_id' => $this->area->id, 'user_id' => $coordinador->id, 'tipo' => 'titular', 'vigente_desde' => '2026-01-01']);
        $ajeno = User::factory()->create()->assignRole('coordinador');
        AreaResponsable::create(['area_id' => Area::factory()->create()->id, 'user_id' => $ajeno->id, 'tipo' => 'titular', 'vigente_desde' => '2026-01-01']);
        $this->redactar();
        $s = DocumentoSaliente::sole();
        $this->actingAs($this->administrativo)->post("/salientes/{$s->id}/revision");

        // El coordinador de otra área no; el director sí podría, como superior (ver el test siguiente).
        $this->actingAs($ajeno)->post("/salientes/{$s->id}/aprobar")->assertForbidden();
        $this->actingAs($coordinador)->post("/salientes/{$s->id}/aprobar")->assertSessionHasNoErrors();
    }

    public function test_la_plantilla_se_llena_con_los_datos_del_expediente(): void
    {
        $plantilla = PlantillaDocumento::create([
            'nombre' => 'Respuesta a requerimiento', 'tipo_documento_id' => $this->oficio->id,
            'asunto' => 'Atención a su {{expediente.documento}}',
            'cuerpo' => "Señores {{remitente}}:\n\nEn atención a su documento ({{expediente.codigo}}), {{area}} remite lo solicitado. {{fecha}}.",
        ]);

        $this->actingAs($this->administrativo)->getJson("/salientes/plantilla/{$plantilla->id}?expediente={$this->expediente->id}&area={$this->area->id}")
            ->assertOk()
            ->assertJsonPath('asunto', 'Atención a su OFICIO N° 120-2026-MPLC')
            ->assertJsonPath('cuerpo', "Señores Municipalidad Provincial:\n\nEn atención a su documento (REG-2026-00038), Dirección General de Administración remite lo solicitado. 6 de octubre de 2026.");
    }

    public function test_se_descarga_en_pdf_y_en_word_y_queda_auditado(): void
    {
        $this->redactar();
        $s = DocumentoSaliente::sole();

        $this->actingAs($this->administrativo)->get("/salientes/{$s->id}/descargar/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $docx = $this->actingAs($this->administrativo)->get("/salientes/{$s->id}/descargar/docx")->assertOk()->getContent();

        $archivo = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($archivo, $docx);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archivo));
        $this->assertStringContainsString('Remitimos la información solicitada.', $zip->getFromName('word/document.xml'));
        $this->assertStringContainsString('Referencia: OFICIO N° 120-2026-MPLC (REG-2026-00038)', $zip->getFromName('word/document.xml'));
        $zip->close();
        unlink($archivo);
        $this->assertDatabaseCount('auditoria', 3);
    }

    public function test_un_coordinador_emite_solo_desde_sus_areas_y_responde_solo_lo_que_ve(): void
    {
        $coordinador = User::factory()->create()->assignRole('coordinador');
        AreaResponsable::create(['area_id' => $this->area->id, 'user_id' => $coordinador->id, 'tipo' => 'titular', 'vigente_desde' => '2026-01-01']);

        $this->redactar(['area_id' => Area::factory()->create()->id], $coordinador)->assertSessionHasErrors('area_id');
        $this->redactar([], $coordinador)->assertSessionHasErrors('expediente_id');
        $this->expediente->update(['area_principal_id' => $this->area->id]);
        $this->redactar([], $coordinador)->assertSessionHasNoErrors();
    }
}
