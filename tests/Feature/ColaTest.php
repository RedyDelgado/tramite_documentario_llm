<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Jobs\ClasificarExpediente;
use App\Jobs\EnviarDocumento;
use App\Jobs\OcrDocumento;
use App\Models\Area;
use App\Models\ClasificacionIa;
use App\Models\Documento;
use App\Models\DocumentoSaliente;
use App\Models\Envio;
use App\Models\Expediente;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Services\EnvioService;
use App\Services\SalienteService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ColaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        Storage::fake('originales');
        Queue::fake();
    }

    private function saliente(array $datos = [], bool $final = true): DocumentoSaliente
    {
        $s = DocumentoSaliente::create($datos + [
            'expediente_id' => Expediente::factory()->create(['estado' => EstadoExpediente::EnAtencion, 'anio' => 2026, 'secuencia' => 38])->id,
            'tipo_documento_id' => TipoDocumento::create(['nombre' => 'Oficio'])->id,
            'area_id' => Area::factory()->create(['siglas' => 'DGA'])->id, 'asunto' => 'Respuesta', 'cuerpo' => 'Texto',
            'destinatarios' => [['email' => 'mesa@muni.gob.pe', 'nombre' => null], ['email' => 'alcalde@muni.gob.pe', 'nombre' => null]],
            'es_respuesta' => true, 'creado_por' => User::factory()->create()->id,
        ]);
        $s->forceFill(['estado' => 'en_revision'])->save();

        $s = app(SalienteService::class)->aprobar($s, User::factory()->create()->assignRole('director'));
        if ($final) {
            // Con el documento final (el Word con su número), sale.
            app(EnvioService::class)->subirFirmado($s, 'Word final', 'oficio.docx');
        }

        return $s;
    }

    /** En producción la cola y los candados de los jobs únicos viven en el mismo Redis. */
    private function perderRedis(): void
    {
        Cache::flush();
        Cache::getStore()->flushLocks();
    }

    public function test_reencolar_no_duplica_lo_que_sigue_en_cola_y_recupera_lo_perdido(): void
    {
        $this->saliente();
        Queue::assertPushed(EnviarDocumento::class, 2);

        // La cola sigue intacta: los dos envíos ya están en ella.
        $this->artisan('colas:reencolar')->assertSuccessful();
        Queue::assertPushed(EnviarDocumento::class, 2);

        // Redis se perdió (cola y candados) después de que salió el primero.
        Envio::orderBy('id')->first()->forceFill(['estado' => 'enviado'])->save();
        $this->perderRedis();
        $this->artisan('colas:reencolar')->expectsOutputToContain('Envíos: 1.')->assertSuccessful();

        Queue::assertPushed(EnviarDocumento::class, 3);
        $pendiente = Envio::where('estado', 'pendiente')->value('id');
        Queue::assertPushed(EnviarDocumento::class, fn (EnviarDocumento $job) => $job->envioId === $pendiente);
        $this->assertDatabaseHas('auditoria', ['accion' => 'colas.reencoladas']);
    }

    public function test_solo_reencola_lo_realmente_pendiente(): void
    {
        $this->saliente(final: false);
        $documento = fn (array $datos) => Documento::create($datos + [
            'expediente_id' => Expediente::factory()->create()->id, 'nombre_original' => 'a.pdf', 'ruta' => 'a.pdf',
            'mime' => 'application/pdf', 'tamano' => 1, 'sha256' => str_repeat('a', 64),
        ]);
        $escaneo = $documento(['texto_extraido' => null]);
        $documento(['texto_extraido' => 'Ya tiene texto']);
        $documento(['texto_extraido' => null, 'mime' => 'message/rfc822']);
        $sinClasificar = Expediente::factory()->create(['estado' => EstadoExpediente::Registrado, 'anio' => 2026, 'secuencia' => 50]);
        Expediente::factory()->create(['estado' => EstadoExpediente::NoTramite]);
        $clasificado = Expediente::factory()->create(['estado' => EstadoExpediente::Registrado, 'anio' => 2026, 'secuencia' => 51]);
        ClasificacionIa::create([
            'expediente_id' => $clasificado->id, 'modelo' => 'similitud', 'version' => 'v1', 'texto_sha256' => str_repeat('b', 64),
            'resultado' => [], 'modo' => 'sombra', 'created_at' => now(),
        ]);

        // Todo lo anterior se perdió con la cola.
        $this->perderRedis();
        Queue::fake();
        $this->artisan('colas:reencolar')->assertSuccessful();

        Queue::assertNotPushed(EnviarDocumento::class);
        Queue::assertPushed(OcrDocumento::class, 1);
        Queue::assertPushed(OcrDocumento::class, fn (OcrDocumento $job) => $job->documentoId === $escaneo->id);
        Queue::assertPushed(ClasificarExpediente::class, fn (ClasificarExpediente $job) => $job->expedienteId === $sinClasificar->id);
        Queue::assertNotPushed(ClasificarExpediente::class, fn (ClasificarExpediente $job) => $job->expedienteId === $clasificado->id);
    }
}
