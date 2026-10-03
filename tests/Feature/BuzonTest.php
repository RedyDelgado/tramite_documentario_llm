<?php

namespace Tests\Feature;

use App\Correo\GmailMailboxDriver;
use App\Correo\MailboxDriver;
use App\Correo\MensajeCrudo;
use App\Jobs\IngestarCorreos;
use App\Models\Configuracion;
use App\Models\User;
use App\Services\BuzonService;
use App\Services\IngestaCorreoService;
use Carbon\CarbonInterface;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as CuentaGoogle;
use RuntimeException;
use Tests\TestCase;

class BuzonTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        config([
            'services.google.client_id' => 'cliente-de-prueba',
            'services.google.client_secret' => 'secreto-de-prueba',
            'tramite.correo.driver' => 'directorio',
            'tramite.correo.activo' => false,
        ]);
        $this->superadmin = User::factory()->create()->assignRole('superadmin');
    }

    private function conectarDesdeGoogle(array $permisos = ['https://www.googleapis.com/auth/gmail.modify'], ?string $token = 'token-permanente')
    {
        $cuenta = CuentaGoogle::fake(['email' => 'mesadepartes@uni.edu.pe'])->setApprovedScopes($permisos)->setRefreshToken($token);
        Socialite::fake('google', $cuenta);

        return $this->actingAs($this->superadmin)->get('/buzon/google/callback');
    }

    public function test_solo_el_superadmin_administra_el_buzon(): void
    {
        $director = User::factory()->create()->assignRole('director');

        $this->actingAs($director)->get('/buzon')->assertForbidden();
        $this->actingAs($director)->post('/buzon/descargar')->assertForbidden();
        $this->actingAs($this->superadmin)->get('/buzon')->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->component('buzon/Index')->where('buzon.conectado', false)->where('buzon.driver', 'directorio'));
    }

    public function test_conectar_guarda_el_token_cifrado_y_el_buzon_pasa_a_gmail(): void
    {
        $this->conectarDesdeGoogle()->assertRedirect('/buzon');

        $guardado = Configuracion::find('correo.gmail.refresh_token')->valor;
        $this->assertNotSame('token-permanente', $guardado);
        $buzon = app(BuzonService::class);
        $this->assertSame('gmail', $buzon->driver());
        $this->assertSame('token-permanente', $buzon->gmail()['refresh_token']);
        $this->assertSame('cliente-de-prueba', $buzon->gmail()['client_id']);
        $this->assertInstanceOf(GmailMailboxDriver::class, app(MailboxDriver::class));

        $auditoria = DB::table('auditoria')->where('accion', 'buzon.conectado')->first();
        $this->assertSame(['cuenta' => 'mesadepartes@uni.edu.pe'], json_decode($auditoria->valor_nuevo, true));
        $this->assertStringNotContainsString('token-permanente', json_encode(DB::table('auditoria')->get()));
    }

    public function test_sin_el_permiso_de_gmail_o_sin_acceso_permanente_no_se_conecta(): void
    {
        $this->conectarDesdeGoogle(['openid', 'email'])->assertRedirect('/buzon');
        $this->conectarDesdeGoogle(token: null)->assertRedirect('/buzon');

        $this->assertFalse(app(BuzonService::class)->conectado());
    }

    public function test_desconectar_vuelve_al_buzon_del_env(): void
    {
        $this->conectarDesdeGoogle();
        $this->post('/buzon/desconectar')->assertRedirect('/buzon');

        $this->assertSame('directorio', app(BuzonService::class)->driver());
        $this->assertNull(Configuracion::find('correo.gmail.cuenta'));
    }

    public function test_descargar_ahora_encola_la_lectura(): void
    {
        Queue::fake();
        $this->actingAs($this->superadmin)->post('/buzon/descargar')->assertRedirect('/buzon');

        Queue::assertPushed(IngestarCorreos::class);
    }

    public function test_la_descarga_automatica_del_panel_manda_sobre_el_env(): void
    {
        $this->assertFalse(app(BuzonService::class)->activo());

        $this->actingAs($this->superadmin)->post('/buzon/activar', ['activo' => true])->assertRedirect('/buzon');
        $this->assertTrue(app(BuzonService::class)->activo());
        $this->assertSame(1, DB::table('auditoria')->where('accion', 'buzon.descarga_automatica')->count());

        $this->post('/buzon/activar', ['activo' => false]);
        $this->assertFalse(app(BuzonService::class)->activo());
    }

    public function test_un_buzon_que_no_se_puede_leer_deja_el_error_a_la_vista(): void
    {
        $roto = new class implements MailboxDriver
        {
            public function pendientes(CarbonInterface $desde, int $limite): iterable
            {
                throw new RuntimeException('invalid_grant');
            }

            public function marcarProcesado(MensajeCrudo $mensaje): void {}

            public function resumen(CarbonInterface $desde): iterable
            {
                return [];
            }
        };

        try {
            app(IngestaCorreoService::class)->procesarPendientes($roto, 10);
            $this->fail('Debió propagar el error.');
        } catch (RuntimeException) {
        }

        $lectura = app(BuzonService::class)->estado()['ultima_lectura'];
        $this->assertSame('invalid_grant', $lectura['error']);
        $this->assertSame(0, $lectura['procesados']);
    }
}
