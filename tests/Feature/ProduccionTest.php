<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Jobs\ClasificarExpediente;
use App\Jobs\OcrDocumento;
use App\Models\Expediente;
use App\Models\User;
use App\Services\OcrService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProduccionTest extends TestCase
{
    use RefreshDatabase;

    public function test_detras_del_proxy_https_la_auditoria_guarda_la_ip_del_usuario(): void
    {
        $this->seed(RolesSeeder::class);
        $director = User::factory()->create()->assignRole('director');
        $expediente = Expediente::factory()->create(['estado' => EstadoExpediente::Registrado, 'anio' => 2026, 'secuencia' => 38]);

        // Caddy, en la red de Docker, reenvía la petición del usuario.
        $this->actingAs($director)
            ->withServerVariables(['REMOTE_ADDR' => '172.18.0.9'])
            ->withHeaders(['X-Forwarded-For' => '200.48.10.7', 'X-Forwarded-Proto' => 'https'])
            ->get("/expedientes/{$expediente->id}")
            ->assertOk();
        $this->assertDatabaseHas('auditoria', ['accion' => 'expediente.consultado', 'ip' => '200.48.10.7']);

        // Desde fuera de la red privada, la cabecera no se cree: no se puede suplantar la IP.
        $this->withServerVariables(['REMOTE_ADDR' => '190.1.2.3'])
            ->withHeaders(['X-Forwarded-For' => '8.8.8.8'])
            ->get("/expedientes/{$expediente->id}")
            ->assertOk();
        $this->assertDatabaseHas('auditoria', ['accion' => 'expediente.consultado', 'ip' => '190.1.2.3']);
    }

    public function test_el_correo_del_sistema_sale_por_gmail_con_la_cuenta_del_buzon(): void
    {
        config(['tramite.correo.gmail' => ['client_id' => 'c', 'client_secret' => 's', 'refresh_token' => 'r', 'usuario' => 'me', 'etiqueta' => 'x']]);
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 't', 'expires_in' => 3600]),
            'gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response(['id' => 'abc123']),
        ]);

        Mail::mailer('gmail')->raw('Tienes 3 expedientes pendientes.', fn ($m) => $m->from('tramite@uni.edu.pe')->to('coordinador@uni.edu.pe')->subject('Resumen diario'));

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'messages/send')
            && str_contains($crudo = base64_decode(strtr($r['raw'], '-_', '+/')), 'Subject: Resumen diario')
            && str_contains($crudo, 'To: coordinador@uni.edu.pe'));
    }

    public function test_ningun_job_se_corta_ni_se_duplica_antes_de_terminar_su_llamada(): void
    {
        $ocr = new OcrDocumento(1);
        $this->assertGreaterThan(OcrService::TIMEOUT, $ocr->timeout);
        $this->assertGreaterThan(120, (new ClasificarExpediente(1))->timeout);

        // Si un job dura más que retry_after, Redis lo entrega a un segundo worker mientras el primero sigue.
        $retryAfter = config('queue.connections.redis.retry_after');
        $this->assertGreaterThan($ocr->timeout, $retryAfter);
        $this->assertGreaterThan(config('horizon.defaults.supervisor-1.timeout'), $retryAfter);
    }
}
