<?php

namespace Tests\Feature;

use App\Correo\DirectorioMailboxDriver;
use App\Correo\GmailMailboxDriver;
use App\Correo\MailboxDriver;
use App\Correo\MensajeCrudo;
use App\Jobs\IngestarCorreos;
use App\Models\Buzon;
use App\Models\CorreoLeido;
use App\Models\Expediente;
use App\Models\User;
use App\Services\BuzonService;
use App\Services\IngestaCorreoService;
use Carbon\CarbonInterface;
use Closure;
use Database\Seeders\ReglasNoTramiteSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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

    private function conectarDesdeGoogle(string $email = 'mesadepartes@uni.edu.pe', array $permisos = ['https://www.googleapis.com/auth/gmail.modify'], ?string $token = 'token-permanente')
    {
        $cuenta = CuentaGoogle::fake(['email' => $email])->setApprovedScopes($permisos)->setRefreshToken($token);
        Socialite::fake('google', $cuenta);

        return $this->actingAs($this->superadmin)->get('/buzon/google/callback');
    }

    public function test_solo_el_superadmin_administra_el_buzon(): void
    {
        $director = User::factory()->create()->assignRole('director');

        $this->actingAs($director)->get('/buzon')->assertForbidden();
        $this->actingAs($director)->post('/buzon/descargar', ['desde' => '2026-10-01'])->assertForbidden();
        $this->actingAs($this->superadmin)->get('/buzon')->assertOk()
            ->assertInertia(fn ($pagina) => $pagina->component('buzon/Index')->where('buzon.cuentas', [])->where('buzon.driver', 'directorio'));
    }

    public function test_se_conectan_varias_cuentas_y_la_primera_es_la_principal(): void
    {
        $this->conectarDesdeGoogle()->assertRedirect('/buzon');
        $this->conectarDesdeGoogle('rdelgado@uni.edu.pe')->assertRedirect('/buzon');

        $this->assertSame(['mesadepartes@uni.edu.pe' => true, 'rdelgado@uni.edu.pe' => false], Buzon::orderBy('id')->pluck('principal', 'cuenta')->all());
        $this->assertNotSame('token-permanente', DB::table('buzones')->value('refresh_token'));
        $buzon = app(BuzonService::class);
        $this->assertSame('token-permanente', $buzon->gmail()['refresh_token']);
        $this->assertSame('mesadepartes@uni.edu.pe', $buzon->principal()->cuenta);
        $this->assertCount(2, $buzon->lectores());
        $this->assertInstanceOf(GmailMailboxDriver::class, $buzon->lectores()[1][1]);
        $this->assertInstanceOf(GmailMailboxDriver::class, app(MailboxDriver::class));
        $this->assertStringNotContainsString('token-permanente', json_encode(DB::table('auditoria')->get()));

        // La segunda pasa a ser la que envía; al quitarla, vuelve a enviar la otra.
        $segunda = Buzon::where('cuenta', 'rdelgado@uni.edu.pe')->sole();
        $this->post("/buzon/cuentas/{$segunda->id}/principal")->assertRedirect('/buzon');
        $this->assertSame('rdelgado@uni.edu.pe', $buzon->principal()->cuenta);
        $this->post("/buzon/cuentas/{$segunda->id}/quitar")->assertRedirect('/buzon');
        $this->assertSame(['mesadepartes@uni.edu.pe' => true], Buzon::pluck('principal', 'cuenta')->all());
        $this->assertDatabaseHas('auditoria', ['accion' => 'buzon.principal']);
    }

    public function test_sin_el_permiso_de_gmail_o_sin_acceso_permanente_no_se_conecta(): void
    {
        $this->conectarDesdeGoogle(permisos: ['openid', 'email'])->assertRedirect('/buzon');
        $this->conectarDesdeGoogle(token: null)->assertRedirect('/buzon');

        $this->assertFalse(app(BuzonService::class)->conectado());
    }

    public function test_un_clic_fija_la_fecha_y_descarga_en_segundo_plano(): void
    {
        Queue::fake();

        $this->actingAs($this->superadmin)->post('/buzon/descargar', ['desde' => '2026-10-01'])->assertRedirect('/buzon');

        Queue::assertPushed(IngestarCorreos::class);
        $this->assertSame('2026-10-01', app(BuzonService::class)->desde()->toDateString());
        $this->post('/buzon/descargar', ['desde' => now()->addDay()->toDateString()])->assertSessionHasErrors('desde');
    }

    public function test_descarga_todo_aunque_supere_el_lote_y_no_repite_lo_ya_leido(): void
    {
        Storage::fake('originales');
        $this->seed(ReglasNoTramiteSeeder::class);
        $buzon = sys_get_temp_dir().'/buzon-'.uniqid();
        File::copyDirectory(base_path('tests/fixtures/correos'), $buzon);
        config(['tramite.correo.directorio' => $buzon, 'tramite.correo.lote' => 3, 'tramite.correo.inicio_operacion' => '2026-10-01']);
        $this->travelTo(now()->setDate(2026, 10, 5));

        // Un solo clic: los lotes se encadenan solos hasta terminar (en el test la cola es síncrona).
        (new IngestarCorreos)->handle(app(BuzonService::class), app(IngestaCorreoService::class));

        $this->assertSame(8, CorreoLeido::where('buzon', 'directorio')->count());
        $this->assertSame(7, Expediente::count());

        // Vaciar las marcas del buzón no hace releer: lo leído lo recuerda la base.
        File::delete(File::glob("{$buzon}/*.procesado"));
        (new IngestarCorreos)->handle(app(BuzonService::class), app(IngestaCorreoService::class));
        $this->assertSame(8, CorreoLeido::count());
        File::deleteDirectory($buzon);
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

    public function test_solo_el_superadmin_detiene_y_puede_reanudar_la_descarga(): void
    {
        Queue::fake();
        $servicio = app(BuzonService::class);
        $servicio->activar(true);
        Cache::put(BuzonService::EN_CURSO, true, 120);
        $director = User::factory()->create()->assignRole('director');
        $this->actingAs($director)->post('/buzon/detener')->assertForbidden();
        $this->assertFalse($servicio->detenida());

        $this->actingAs($this->superadmin)->post('/buzon/detener')->assertRedirect('/buzon');
        $this->assertTrue($servicio->detenida());
        $this->assertFalse($servicio->activo());
        $this->assertDatabaseHas('auditoria', ['accion' => 'buzon.descarga_detenida', 'usuario_id' => $this->superadmin->id]);
        // Un trabajo que ya estaba en cola también debe respetar la detención.
        $this->mock(IngestaCorreoService::class)->shouldNotReceive('procesarPendientes');
        (new IngestarCorreos)->handle($servicio, app(IngestaCorreoService::class));
        $this->assertFalse(Cache::has(BuzonService::EN_CURSO));
        Queue::assertNotPushed(IngestarCorreos::class);

        $this->post('/buzon/descargar', ['desde' => today()->toDateString()])->assertRedirect('/buzon');
        $this->assertFalse($servicio->detenida());
        Queue::assertPushed(IngestarCorreos::class, 1);
    }

    public function test_detener_entre_correos_conserva_el_ingresado_y_no_encadena_otro_lote(): void
    {
        Queue::fake();
        Storage::fake('originales');
        $directorio = sys_get_temp_dir().'/buzon-detener-'.uniqid();
        File::copyDirectory(base_path('tests/fixtures/correos'), $directorio);
        config(['tramite.correo.lote' => 3]);
        $lector = new class($directorio) extends DirectorioMailboxDriver
        {
            public function marcarProcesado(MensajeCrudo $mensaje): void
            {
                parent::marcarProcesado($mensaje);
                app(BuzonService::class)->detener();
            }
        };
        $this->app->instance(MailboxDriver::class, $lector);

        try {
            (new IngestarCorreos)->handle(app(BuzonService::class), app(IngestaCorreoService::class));

            $this->assertSame(1, CorreoLeido::count());
            $this->assertSame(1, Expediente::count());
            $this->assertCount(1, File::glob($directorio.'/*.procesado'));
            Queue::assertNotPushed(IngestarCorreos::class);
            $this->assertFalse(Cache::has(BuzonService::EN_CURSO));
            $this->assertTrue(app(BuzonService::class)->detenida());

            // Reanudar con el lector normal continúa con el siguiente correo.
            config(['tramite.correo.lote' => 1]);
            app(BuzonService::class)->descargar('2026-01-01');
            $this->app->instance(MailboxDriver::class, new DirectorioMailboxDriver($directorio));
            (new IngestarCorreos)->handle(app(BuzonService::class), app(IngestaCorreoService::class));
            $this->assertSame(2, CorreoLeido::count());
            $this->assertSame(2, Expediente::count());
        } finally {
            File::deleteDirectory($directorio);
        }
    }

    public function test_un_trabajo_pendiente_no_oculta_que_otro_aun_esta_terminando(): void
    {
        app(BuzonService::class)->detener();
        Cache::put(BuzonService::EN_CURSO, true, 120);
        $candado = Cache::lock('ingesta-correo', 90);
        $this->assertTrue($candado->get());

        try {
            (new IngestarCorreos)->handle(app(BuzonService::class), app(IngestaCorreoService::class));
            $this->assertTrue(Cache::has(BuzonService::EN_CURSO));
        } finally {
            $candado->release();
        }
        (new IngestarCorreos)->handle(app(BuzonService::class), app(IngestaCorreoService::class));
        $this->assertFalse(Cache::has(BuzonService::EN_CURSO));
    }

    public function test_reanudar_conserva_la_fecha_original_y_solo_lo_hace_el_superadmin(): void
    {
        Queue::fake();
        $servicio = app(BuzonService::class);
        $servicio->descargar('2026-01-01');
        $servicio->detener();
        (new IngestarCorreos)->handle($servicio, app(IngestaCorreoService::class));
        $director = User::factory()->create()->assignRole('director');
        $this->actingAs($director)->post('/buzon/reanudar')->assertForbidden();
        $this->actingAs($director)->post('/buzon/finalizar')->assertForbidden();

        $this->actingAs($this->superadmin)->post('/buzon/reanudar', ['desde' => '2026-02-01'])->assertRedirect('/buzon');
        $this->assertSame('2026-01-01', $servicio->desde()->toDateString());
        $this->assertFalse($servicio->detenida());
        $this->assertFalse($servicio->estado()['finalizada']);
        $this->assertFalse($servicio->activo());
        Queue::assertPushed(IngestarCorreos::class, 2);
        $this->assertDatabaseHas('auditoria', ['accion' => 'buzon.descarga_reanudada']);
    }

    public function test_finalizar_conserva_los_datos_bloquea_trabajos_antiguos_y_permite_reiniciar(): void
    {
        Queue::fake();
        $expediente = Expediente::factory()->create();
        CorreoLeido::create(['buzon' => 'directorio', 'uid' => 'ya-importado']);
        $servicio = app(BuzonService::class);
        $servicio->detener();

        $this->actingAs($this->superadmin)->post('/buzon/finalizar')->assertRedirect('/buzon');
        $this->assertTrue($servicio->estado()['finalizada']);
        $this->assertTrue($servicio->detenida());
        (new IngestarCorreos)->handle($servicio, app(IngestaCorreoService::class));
        Queue::assertNotPushed(IngestarCorreos::class);
        $this->assertTrue($expediente->fresh()->exists);
        $this->assertSame(1, CorreoLeido::count());
        $this->assertDatabaseHas('auditoria', ['accion' => 'buzon.descarga_finalizada']);

        $this->post('/buzon/descargar', ['desde' => '2026-02-01'])->assertRedirect('/buzon');
        $this->assertSame('2026-02-01', $servicio->desde()->toDateString());
        $this->assertFalse($servicio->detenida());
        $this->assertFalse($servicio->estado()['finalizada']);
        $this->assertSame(1, CorreoLeido::count());
        Queue::assertPushed(IngestarCorreos::class, 1);
    }

    public function test_no_se_reanuda_ni_finaliza_mientras_termina_el_correo_actual(): void
    {
        Queue::fake();
        $servicio = app(BuzonService::class);
        $servicio->detener();
        Cache::put(BuzonService::EN_CURSO, true, 120);

        foreach (['reanudar', 'finalizar', 'descargar'] as $accion) {
            $this->actingAs($this->superadmin)->from('/buzon')
                ->post('/buzon/'.$accion, ['desde' => '2026-01-01'])->assertRedirect('/buzon');
        }
        $this->assertTrue($servicio->detenida());
        $this->assertFalse($servicio->estado()['finalizada']);
        Queue::assertNotPushed(IngestarCorreos::class);
    }

    public function test_un_buzon_que_no_se_puede_leer_deja_el_error_a_la_vista(): void
    {
        $roto = new class implements MailboxDriver
        {
            public function pendientes(CarbonInterface $desde, int $limite, ?Closure $leido = null): iterable
            {
                throw new RuntimeException('invalid_grant');
            }

            public function marcarProcesado(MensajeCrudo $mensaje): void {}

            public function resumen(CarbonInterface $desde): iterable
            {
                return [];
            }
        };
        $cuenta = Buzon::create(['cuenta' => 'caida@uni.edu.pe', 'refresh_token' => 'x', 'principal' => true]);

        try {
            app(IngestaCorreoService::class)->procesarPendientes($roto, 10, $cuenta->cuenta, $cuenta);
            $this->fail('Debió propagar el error.');
        } catch (RuntimeException) {
        }

        $this->assertSame('invalid_grant', $cuenta->fresh()->ultima_lectura['error']);
    }
}
