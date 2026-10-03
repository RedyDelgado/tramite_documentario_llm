<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Enums\OrigenExpediente;
use App\Models\Emisor;
use App\Models\Expediente;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Services\ExtraccionService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RegistroFisicoTest extends TestCase
{
    use RefreshDatabase;

    private User $administrativo;

    private Emisor $emisor;

    private TipoDocumento $multiple;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        Storage::fake('originales');
        $this->travelTo('2026-08-17 10:00:00');
        $this->administrativo = User::factory()->create()->assignRole('administrativo');
        $this->emisor = Emisor::create(['nombre' => 'Dirección General de Administración', 'tipo' => 'interno']);
        Emisor::create(['nombre' => 'Dirección', 'tipo' => 'interno']);
        TipoDocumento::create(['nombre' => 'Oficio']);
        $this->multiple = TipoDocumento::create(['nombre' => 'Oficio múltiple']);
    }

    /** PDF de una página con capa de texto (WinAnsi para las tildes), como sale de un escáner con OCR o de Word. */
    public static function pdf(array $lineas): string
    {
        $flujo = 'BT /F1 12 Tf 72 720 Td 16 TL';
        foreach ($lineas as $linea) {
            $flujo .= ' ('.addcslashes(mb_convert_encoding($linea, 'Windows-1252', 'UTF-8'), '()\\').") '";
        }
        $flujo .= ' ET';
        $objetos = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($flujo)." >>\nstream\n{$flujo}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objetos as $i => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$obj}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref
0 '.(count($objetos) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }

        return $pdf.'trailer
<< /Size '.(count($objetos) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    private function oficio(string $numero = '045-2026-UNIQ/DGA'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('oficio.pdf', self::pdf([
            'UNIVERSIDAD NACIONAL',
            "OFICIO MÚLTIPLE N° {$numero}",
            'Quillabamba, 12 de agosto de 2026',
            'Señor: Director de la Escuela',
            'ASUNTO: Encuesta de sostenibilidad ambiental',
            'Dirección General de Administración',
        ]));
    }

    private function prellenar(UploadedFile $archivo): TestResponse
    {
        return $this->actingAs($this->administrativo)->postJson('/registro/prellenar', ['archivo' => $archivo]);
    }

    private function registrar(string $sha256, array $extra = []): TestResponse
    {
        return $this->actingAs($this->administrativo)->post('/registro', $extra + [
            'sha256' => $sha256, 'asunto' => 'Encuesta de sostenibilidad ambiental', 'emisor_id' => $this->emisor->id,
            'tipo_documento_id' => $this->multiple->id, 'numero_documento' => 'Oficio Múltiple No. 045 – 2026 - UNIQ / DGA',
            'fecha_documento' => '2026-08-12', 'folios' => 1, 'requiere_respuesta' => true,
        ]);
    }

    public function test_el_formulario_de_un_pdf_queda_prellenado_con_los_campos_extraidos(): void
    {
        $this->prellenar($this->oficio())->assertOk()
            ->assertJsonPath('paginas', 1)
            ->assertJsonPath('campos.tipo_documento_id', $this->multiple->id)
            ->assertJsonPath('campos.numero_documento_original', 'OFICIO MÚLTIPLE N° 045-2026-UNIQ/DGA')
            ->assertJsonPath('campos.fecha_documento', '2026-08-12')
            ->assertJsonPath('campos.asunto', 'Encuesta de sostenibilidad ambiental')
            ->assertJsonPath('campos.emisor_id', $this->emisor->id)
            ->assertJsonPath('campos.folios', 1)
            ->assertJsonPath('mismo_archivo', null);
    }

    public function test_un_fut_queda_prellenado_aunque_no_tenga_numero_ni_asunto(): void
    {
        $fut = TipoDocumento::create(['nombre' => 'Solicitud (FUT)']);
        $estudiante = Emisor::create(['nombre' => 'Luz Marina Quispe Ccama', 'tipo' => 'interno']);
        $escaneo = UploadedFile::fake()->createWithContent('fut.pdf', self::pdf([
            'UNIVERSIDAD ANDINA DEL CUSCO · FILIAL QUILLABAMBA',
            'FORMULARIO ÚNICO DE TRÁMITE (FUT)',
            'Yo, Luz Marina Quispe Ccama, con código 2021400123, estudiante de Enfermería,',
            'SOLICITO: Constancia de estudios para trámite de beca',
            'Adjunto voucher de pago N° 0045871.',
            'Quillabamba, 2 de octubre de 2026',
        ]));

        $this->prellenar($escaneo)->assertOk()
            ->assertJsonPath('campos.tipo_documento_id', $fut->id)
            ->assertJsonPath('campos.asunto', 'Constancia de estudios para trámite de beca')
            ->assertJsonPath('campos.emisor_id', $estudiante->id)
            ->assertJsonPath('campos.fecha_documento', '2026-10-02')
            // El N° del voucher no es el número del documento.
            ->assertJsonPath('campos.numero_documento_original', null);
    }

    public function test_registrar_asigna_numero_y_conserva_el_hash_del_original(): void
    {
        $sha = $this->prellenar($this->oficio())->json('sha256');

        $this->registrar($sha)->assertSessionHasNoErrors()->assertRedirect();

        $expediente = Expediente::sole();
        $this->assertSame([OrigenExpediente::Fisico, EstadoExpediente::Registrado, 'N°00038'], [$expediente->origen, $expediente->estado, $expediente->numero_registro]);
        $this->assertSame('OFICIO MULTIPLE N° 045-2026-UNIQ/DGA', $expediente->numero_documento);
        $documento = $expediente->documentos()->sole();
        $this->assertFalse($documento->es_adjunto);
        $this->assertSame($sha, $documento->sha256);
        $this->assertSame($sha, hash('sha256', Storage::disk('originales')->get($documento->ruta)));
    }

    public function test_un_tramite_en_curso_conserva_su_numero_y_su_fecha_sin_consumir_el_correlativo(): void
    {
        $sha = $this->prellenar($this->oficio('012-2026-UNIQ/DGA'))->json('sha256');

        $this->registrar($sha, ['numero_documento' => 'Oficio Múltiple N° 012-2026-UNIQ/DGA', 'fecha_documento' => '2026-08-03', 'en_curso' => true, 'numero_papel' => 12, 'fecha_ingreso' => '2026-08-04'])
            ->assertSessionHasNoErrors();

        $enCurso = Expediente::sole();
        $this->assertSame(['N°00012', 'REG-2026-00012', '2026-08-04'], [$enCurso->numero_registro, $enCurso->codigo, $enCurso->fecha_ingreso->toDateString()]);
        $this->assertDatabaseHas('auditoria', ['accion' => 'registro.asignado', 'entidad_id' => (string) $enCurso->id]);

        // El correlativo del sistema no se movió: lo nuevo sigue desde el N°00038.
        $this->registrar($this->prellenar($this->oficio())->json('sha256'))->assertSessionHasNoErrors();
        $this->assertSame('N°00038', Expediente::latest('id')->first()->numero_registro);
    }

    public function test_un_tramite_en_curso_no_acepta_un_numero_usado_ni_del_sistema_ni_una_fecha_futura(): void
    {
        $this->registrar($this->prellenar($this->oficio('012-2026-UNIQ/DGA'))->json('sha256'), ['numero_documento' => 'Oficio 012-2026', 'en_curso' => true, 'numero_papel' => 12, 'fecha_ingreso' => '2026-08-04']);
        $sha = $this->prellenar($this->oficio())->json('sha256');

        $this->registrar($sha, ['en_curso' => true, 'numero_papel' => 12, 'fecha_ingreso' => '2026-08-04'])->assertSessionHasErrors('numero_papel');
        $this->registrar($sha, ['en_curso' => true, 'numero_papel' => 38, 'fecha_ingreso' => '2026-08-04'])->assertSessionHasErrors('numero_papel');
        $this->registrar($sha, ['en_curso' => true, 'numero_papel' => 13, 'fecha_ingreso' => '2026-08-18'])->assertSessionHasErrors('fecha_ingreso');
        $this->registrar($sha, ['en_curso' => true])->assertSessionHasErrors(['numero_papel', 'fecha_ingreso']);
        $this->assertSame(1, Expediente::count());
    }

    public function test_registrar_de_nuevo_un_documento_ya_ingresado_se_bloquea_y_muestra_el_numero(): void
    {
        $this->registrar($this->prellenar($this->oficio())->json('sha256'));
        $primero = Expediente::sole();

        // Otro escaneo del mismo oficio (otro archivo), con el número escrito de otra forma.
        $otro = $this->prellenar(UploadedFile::fake()->createWithContent('copia.pdf', self::pdf(['OFICIO MULTIPLE NRO. 045-2026-UNIQ/DGA'])))->json('sha256');
        $this->registrar($otro, ['numero_documento' => 'oficio múltiple N.º 045-2026-UNIQ/DGA', 'confirmar_duplicado' => true])
            ->assertSessionHasErrors(['numero_documento' => "Ya registrado como N°00038 ({$primero->codigo}).", 'duplicado_id' => (string) $primero->id]);
        $this->assertSame(1, Expediente::count());

        // Anulado el primero, el documento se puede registrar otra vez.
        $primero->forceFill(['estado' => EstadoExpediente::Anulado])->save();
        $this->registrar($otro, ['confirmar_duplicado' => true])->assertSessionHasNoErrors();
    }

    public function test_el_mismo_archivo_avisa_y_se_registra_al_confirmar(): void
    {
        $sha = $this->prellenar($this->oficio())->json('sha256');
        $this->registrar($sha);

        $this->assertNotNull($this->prellenar($this->oficio())->json('mismo_archivo.id'));
        $this->registrar($sha, ['numero_documento' => '046-2026-UNIQ/DGA'])->assertSessionHasErrors('duplicado');
        $this->registrar($sha, ['numero_documento' => '046-2026-UNIQ/DGA', 'confirmar_duplicado' => true])->assertSessionHasNoErrors();
        $this->assertSame(2, Expediente::count());
    }

    public function test_cambiar_los_folios_exige_motivo(): void
    {
        $sha = $this->prellenar($this->oficio())->json('sha256');

        $this->registrar($sha, ['folios' => 3])->assertSessionHasErrors('motivo_folios');
        $this->registrar($sha, ['folios' => 3, 'motivo_folios' => 'Dos anexos sin escanear'])->assertSessionHasNoErrors();
    }

    public function test_solo_quien_registra_puede_registrar_papel(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('coordinador'))->get('/registro/nuevo')->assertForbidden();
        $this->actingAs(User::factory()->create()->assignRole('director'))->postJson('/registro/prellenar', ['archivo' => $this->oficio()])->assertForbidden();
    }

    public function test_normaliza_el_numero_de_documento(): void
    {
        $this->assertSame('OFICIO MULTIPLE N° 045-2026-UNIQ/DGA', ExtraccionService::normalizarNumero('Oficio Múltiple No. 045 – 2026 - UNIQ / DGA'));
        $this->assertSame('CARTA N° 12-2026', ExtraccionService::normalizarNumero('carta  Nº12-2026'));
        $this->assertSame('INFORME N° 3-2026', ExtraccionService::normalizarNumero('Informe N.° 3 - 2026'));
    }
}
