<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Models\Auditoria;
use App\Models\Correo;
use App\Models\Documento;
use App\Models\Expediente;
use App\Services\ExpedienteService;
use App\Services\IngestaCorreoService;
use Database\Seeders\ReglasNoTramiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IngestaCorreoTest extends TestCase
{
    use RefreshDatabase;

    private IngestaCorreoService $ingesta;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('originales');
        $this->seed(ReglasNoTramiteSeeder::class);
        config(['tramite.correo.inicio_operacion' => '2026-10-01']);
        $this->travelTo(now()->setDate(2026, 10, 5));
        $this->ingesta = app(IngestaCorreoService::class);
    }

    private function eml(string $nombre): string
    {
        return File::get(base_path("tests/fixtures/correos/{$nombre}.eml"));
    }

    private function ingresar(string $nombre): Expediente
    {
        return $this->ingesta->procesar($this->eml($nombre), $nombre);
    }

    public function test_un_oficio_con_pdf_crea_un_expediente_por_revisar_con_sus_originales(): void
    {
        $expediente = $this->ingresar('oficio-con-pdf');

        $this->assertSame(EstadoExpediente::PorRevisar, $expediente->estado);
        $this->assertNull($expediente->numero_registro);
        $this->assertSame('OFICIO N° 123-2026-MDE: Invitación a ceremonia de aniversario', $expediente->asunto);
        $this->assertSame('mesadepartes@munidemo.example', $expediente->remitente_email);
        $this->assertSame('Municipalidad Distrital de Ejemplo', $expediente->remitente_nombre);

        $correo = Correo::sole();
        $this->assertSame('oficio-con-pdf@pruebas.example', $correo->message_id);
        $this->assertSame(hash('sha256', $this->eml('oficio-con-pdf')), $correo->sha256);
        $this->assertSame($this->eml('oficio-con-pdf'), Storage::disk('originales')->get($correo->ruta_eml));

        $pdf = Documento::sole();
        $this->assertSame('Oficio 123-2026-MDE.pdf', $pdf->nombre_original);
        $this->assertSame('application/pdf', $pdf->mime);
        $this->assertSame(hash_file('sha256', base_path('tests/fixtures/correos/oficio.pdf')), $pdf->sha256);
        $this->assertSame($pdf->sha256, hash('sha256', Storage::disk('originales')->get($pdf->ruta)));
        $this->assertStringContainsString('ceremonia de aniversario', $pdf->texto_extraido);
    }

    public function test_reprocesar_el_mismo_correo_no_duplica(): void
    {
        $primero = $this->ingresar('oficio-con-pdf');
        $segundo = $this->ingresar('oficio-con-pdf');

        $this->assertTrue($primero->is($segundo));
        $this->assertSame([1, 1, 1], [Expediente::count(), Correo::count(), Documento::count()]);
    }

    public function test_sin_message_id_tambien_es_idempotente(): void
    {
        $this->ingresar('sin-message-id');
        $this->ingresar('sin-message-id');

        $this->assertSame(1, Correo::count());
    }

    public function test_una_respuesta_en_el_hilo_se_anexa_al_expediente(): void
    {
        $original = $this->ingresar('oficio-con-pdf');
        $respuesta = $this->ingresar('respuesta-en-hilo');

        $this->assertTrue($original->is($respuesta));
        $this->assertSame(1, Expediente::count());
        $this->assertSame(2, $original->correos()->count());
    }

    public function test_una_respuesta_con_el_codigo_en_el_asunto_se_anexa_al_expediente_correcto(): void
    {
        $numerado = app(ExpedienteService::class)->confirmar(Expediente::factory()->create());
        $this->assertSame('REG-2026-00038', $numerado->codigo);

        $this->assertTrue($numerado->is($this->ingresar('respuesta-con-codigo')));
    }

    public function test_un_boletin_queda_como_no_tramite_y_no_consume_numero(): void
    {
        $boletin = $this->ingresar('boletin');

        $this->assertSame(EstadoExpediente::NoTramite, $boletin->estado);
        $this->assertNull($boletin->secuencia);
        $this->assertSame('Boletines con enlace de baja', Auditoria::where('accion', 'expediente.creado')->sole()->valor_nuevo['regla_no_tramite']);
    }

    public function test_un_reenvio_toma_al_remitente_original(): void
    {
        $expediente = $this->ingresar('reenvio');

        $this->assertSame('oficios@redsalud.example', $expediente->remitente_email);
        $this->assertSame('Red de Salud de Ejemplo', $expediente->remitente_nombre);
        $this->assertFalse($expediente->remitente_por_confirmar);
        $this->assertTrue(Correo::sole()->es_reenvio);
    }

    public function test_un_reenvio_sin_remitente_reconocible_queda_por_confirmar(): void
    {
        $expediente = $this->ingresar('reenvio-sin-remitente');

        $this->assertSame('secretaria@universidad.example', $expediente->remitente_email);
        $this->assertTrue($expediente->remitente_por_confirmar);
    }

    public function test_lo_anterior_a_la_puesta_en_marcha_entra_como_historico(): void
    {
        $this->assertSame(EstadoExpediente::Historico, $this->ingresar('antiguo')->estado);
    }

    public function test_el_ingreso_queda_auditado_con_los_hashes(): void
    {
        $expediente = $this->ingresar('oficio-con-pdf');

        $evento = Auditoria::where('accion', 'correo.ingresado')->sole();
        $this->assertSame($expediente->id, $evento->valor_nuevo['expediente_id']);
        $this->assertSame(Correo::sole()->sha256, $evento->valor_nuevo['sha256']);
        $this->assertSame(Documento::sole()->sha256, $evento->valor_nuevo['adjuntos'][0]['sha256']);
        $this->artisan('auditoria:verificar')->assertSuccessful();
    }

    public function test_el_buzon_de_carpeta_procesa_una_vez_y_marca_lo_procesado(): void
    {
        $buzon = sys_get_temp_dir().'/buzon-'.uniqid();
        File::copyDirectory(base_path('tests/fixtures/correos'), $buzon);
        config(['tramite.correo.directorio' => $buzon]);

        $this->artisan('correo:importar')->expectsOutputToContain('Procesados: 8.')->assertSuccessful();
        $this->artisan('correo:importar')->expectsOutputToContain('Procesados: 0.')->assertSuccessful();

        $this->assertFileExists("{$buzon}/oficio-con-pdf.eml");
        $this->assertFileExists("{$buzon}/oficio-con-pdf.eml.procesado");
        // 8 correos: la respuesta en hilo se anexa al oficio y el código REG no existe aún.
        $this->assertSame(7, Expediente::count());

        File::deleteDirectory($buzon);
    }

    public function test_las_estadisticas_cuentan_sin_guardar_nada(): void
    {
        config(['tramite.correo.directorio' => base_path('tests/fixtures/correos')]);

        $this->artisan('correo:estadisticas', ['--desde' => '2026-01-01'])
            ->expectsOutputToContain('8 correos desde 2026-01-01')
            ->assertSuccessful();

        $this->assertSame(0, Correo::count());
        Storage::disk('originales')->assertDirectoryEmpty('/');
    }
}
