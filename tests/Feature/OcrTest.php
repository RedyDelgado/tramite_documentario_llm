<?php

namespace Tests\Feature;

use App\Jobs\OcrDocumento;
use App\Models\Documento;
use App\Models\Expediente;
use App\Services\AuditoriaService;
use App\Services\OcrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OcrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('originales');
        Storage::disk('originales')->put('escaneos/a.pdf', '%PDF-escaneo');
    }

    private function documento(array $datos = []): Documento
    {
        return Documento::create($datos + [
            'expediente_id' => Expediente::factory()->create()->id, 'nombre_original' => 'oficio.pdf', 'ruta' => 'escaneos/a.pdf',
            'mime' => 'application/pdf', 'tamano' => 12, 'sha256' => str_repeat('a', 64), 'texto_extraido' => null,
        ]);
    }

    public function test_un_escaneo_queda_buscable_por_su_contenido(): void
    {
        Http::fake(['ai:8000/ocr' => Http::response(['texto' => "OFICIO MÚLTIPLE N° 045-2026\nEncuesta de sostenibilidad", 'paginas' => 3])]);

        $documento = $this->documento();

        $documento->refresh();
        $this->assertTrue($documento->texto_por_ocr);
        $this->assertSame(3, $documento->paginas);
        $this->assertStringContainsString('Encuesta de sostenibilidad', $documento->expediente->toSearchableArray()['texto']);
        Http::assertSent(fn ($r) => $r->hasHeader('Content-Type', 'application/pdf') && $r->body() === '%PDF-escaneo');
        $this->assertDatabaseHas('auditoria', ['accion' => 'documento.ocr']);
    }

    public function test_solo_se_encola_lo_que_no_tiene_texto_y_es_escaneable(): void
    {
        Queue::fake();

        $this->documento(['texto_extraido' => 'ya tiene texto']);
        $this->documento(['mime' => 'application/zip']);
        $escaneo = $this->documento();

        Queue::assertPushed(OcrDocumento::class, 1);
        Queue::assertPushed(OcrDocumento::class, fn (OcrDocumento $job) => $job->documentoId === $escaneo->id);
    }

    public function test_si_el_servicio_de_ia_esta_caido_el_job_reintenta_sin_perder_el_documento(): void
    {
        Queue::fake();
        $documento = $this->documento();
        Http::fake(['ai:8000/ocr' => fn () => throw new ConnectionException('caído')]);

        $this->expectException(ConnectionException::class);
        try {
            (new OcrDocumento($documento->id))->handle(app(OcrService::class), app(AuditoriaService::class));
        } finally {
            $this->assertNull($documento->fresh()->texto_extraido);
        }
    }
}
