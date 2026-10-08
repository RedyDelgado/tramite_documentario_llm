<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\DocumentoSaliente;
use App\Models\Emisor;
use App\Models\Expediente;
use App\Models\TipoDocumento;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

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
            'asunto' => 'Respuesta al requerimiento', 'cuerpo' => 'Se adjunta la respuesta.',
            'archivo' => UploadedFile::fake()->create('respuesta.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'destinatarios' => [['email' => 'mesa@municipalidad.gob.pe', 'nombre' => 'Mesa de partes']],
            'es_respuesta' => true, 'requiere_respuesta' => false, 'plazo_respuesta_dias' => null,
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
        // Aprobado recibe su número y espera el documento final que lo lleva; el borrador revisado queda guardado.
        $this->assertSame(['aprobado', 'OFICIO N.º 001-2026-DGA', $this->director->id], [$s->estado, $s->numero, $s->aprobado_por]);
        $this->assertSame(['respuesta.docx', $s->sha256_borrador], [$s->nombre_borrador, hash('sha256', Storage::disk('originales')->get($s->ruta_borrador))]);
        $this->assertDatabaseHas('auditoria', ['accion' => 'saliente.aprobado', 'usuario_id' => $this->director->id]);
        // Lo aprobado no se edita.
        $this->actingAs($this->administrativo)->get("/salientes/{$s->id}/edit")->assertForbidden();
    }

    public function test_sin_borrador_no_se_guarda_y_solo_se_admite_word_o_pdf(): void
    {
        $this->redactar(['archivo' => null])->assertSessionHasErrors('archivo');
        $this->redactar(['archivo' => UploadedFile::fake()->create('foto.png', 20, 'image/png')])->assertSessionHasErrors('archivo');
        $this->redactar(['archivo' => UploadedFile::fake()->create('oficio.pdf', 20, 'application/pdf')])->assertSessionHasNoErrors();
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

    public function test_se_descargan_el_borrador_y_el_final_tal_como_se_subieron_y_queda_auditado(): void
    {
        $this->redactar();
        $s = DocumentoSaliente::sole();

        $this->actingAs($this->administrativo)->get("/salientes/{$s->id}/descargar/borrador")->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')
            ->assertHeader('Content-Disposition', 'attachment; filename="borrador-'.$s->id.'.docx"');
        $this->actingAs($this->administrativo)->get("/salientes/{$s->id}/descargar/final")->assertNotFound();
        $this->assertDatabaseHas('auditoria', ['accion' => 'saliente.descargado']);
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
