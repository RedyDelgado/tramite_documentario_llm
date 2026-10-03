<?php

namespace Tests\Feature;

use App\Jobs\OcrDocumento;
use App\Models\Correo;
use App\Models\Documento;
use App\Models\User;
use App\Services\AntivirusService;
use App\Services\IngestaCorreoService;
use Closure;
use Database\Seeders\ReglasNoTramiteSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class AntivirusTest extends TestCase
{
    use RefreshDatabase;

    // Firma de prueba estándar: todos los antivirus la detectan y no es dañina.
    private const EICAR = 'X5O!P%@AP[4\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolesSeeder::class, ReglasNoTramiteSeeder::class]);
        Storage::fake('originales');
        Queue::fake();
        config(['tramite.correo.inicio_operacion' => '2026-10-01']);
        $this->travelTo(now()->setDate(2026, 10, 5));
    }

    /** @param Closure(string): ?string $respuesta */
    private function antivirus(Closure $respuesta): void
    {
        $this->app->instance(AntivirusService::class, new class($respuesta) extends AntivirusService
        {
            public function __construct(private Closure $respuesta) {}

            public function amenaza(string $contenido): ?string
            {
                return ($this->respuesta)($contenido);
            }
        });
    }

    public function test_un_adjunto_infectado_queda_en_cuarentena_sin_procesar_ni_descargarse(): void
    {
        // El PDF del oficio hace de archivo infectado.
        $this->antivirus(fn (string $c) => str_starts_with($c, '%PDF') ? 'Win.Test.EICAR_HDB-1' : null);
        $expediente = app(IngestaCorreoService::class)->procesar(File::get(base_path('tests/fixtures/correos/oficio-con-pdf.eml')), 'uid-1');

        $documento = Documento::sole();
        $this->assertSame('Win.Test.EICAR_HDB-1', $documento->amenaza);
        $this->assertStringStartsWith('cuarentena/', $documento->ruta);
        $this->assertNull($documento->texto_extraido);
        Queue::assertNotPushed(OcrDocumento::class);
        $this->assertDatabaseHas('auditoria', ['accion' => 'documento.en_cuarentena', 'entidad_id' => (string) $documento->id]);

        $director = User::factory()->create()->assignRole('director');
        $this->actingAs($director)->get("/documentos/{$documento->id}/descargar")->assertRedirect()->assertInertiaFlash('toast.tipo', 'error');
        $this->actingAs($director)->get('/correos/'.Correo::sole()->id.'/eml')->assertRedirect()->assertInertiaFlash('toast.tipo', 'error');
        $this->actingAs($director)->get("/expedientes/{$expediente->id}")
            ->assertInertia(fn ($page) => $page->where('expediente.documentos.0.amenaza', 'Win.Test.EICAR_HDB-1'));
    }

    public function test_con_el_antivirus_caido_el_correo_no_entra_y_se_reintenta(): void
    {
        $this->antivirus(fn () => throw new RuntimeException('El antivirus no responde'));

        $this->expectException(RuntimeException::class);
        try {
            app(IngestaCorreoService::class)->procesar(File::get(base_path('tests/fixtures/correos/oficio-con-pdf.eml')), 'uid-1');
        } finally {
            // Nada a medias: la próxima pasada lo procesa desde cero.
            $this->assertSame(0, Correo::count());
        }
    }

    public function test_una_subida_infectada_se_rechaza_y_con_el_antivirus_caido_se_pide_reintentar(): void
    {
        $administrativo = User::factory()->create()->assignRole('administrativo');
        $escaneo = UploadedFile::fake()->createWithContent('oficio.pdf', "%PDF-1.4\n".self::EICAR);

        $this->antivirus(fn () => 'Win.Test.EICAR_HDB-1');
        $this->actingAs($administrativo)->postJson('/registro/prellenar', ['archivo' => $escaneo])
            ->assertJsonValidationErrors(['archivo' => 'El archivo contiene «Win.Test.EICAR_HDB-1» y no se guardó.']);

        $this->antivirus(fn () => throw new RuntimeException('caído'));
        $this->actingAs($administrativo)->postJson('/registro/prellenar', ['archivo' => $escaneo])
            ->assertJsonValidationErrors(['archivo' => 'No se pudo analizar el archivo con el antivirus; intenta de nuevo en unos minutos.']);

        $this->assertSame([], Storage::disk('originales')->allFiles());
    }

    public function test_el_protocolo_funciona_contra_clamd_real(): void
    {
        config(['tramite.antivirus.host' => 'clamav']);
        $socket = @fsockopen('clamav', 3310, $codigo, $error, 2);
        if (! $socket) {
            $this->markTestSkipped('Requiere ClamAV: docker compose --profile clamav up -d');
        }
        fclose($socket);

        $antivirus = new AntivirusService;
        $this->assertStringContainsStringIgnoringCase('eicar', (string) $antivirus->amenaza(self::EICAR));
        $this->assertNull($antivirus->amenaza('Oficio múltiple N° 045-2026: encuesta de sostenibilidad.'));
        // Más de un trozo del protocolo (64 KB): el archivo llega completo.
        $this->assertNull($antivirus->amenaza(str_repeat('a', 200_000)));
    }
}
