<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as CuentaGoogle;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        config([
            'tramite.google_dominio' => 'uni.edu.pe',
            'services.google.client_id' => 'cliente-de-prueba',
            'services.google.client_secret' => 'secreto-de-prueba',
        ]);
    }

    private function volverDeGoogle(array $cuenta): TestResponse
    {
        Socialite::fake('google', CuentaGoogle::fake([
            'email' => 'ana@uni.edu.pe',
            'hd' => 'uni.edu.pe',
            'email_verified' => true,
            ...$cuenta,
        ]));

        return $this->get('/auth/google/callback');
    }

    private function ultimaAuditoria(): object
    {
        return DB::table('auditoria')->orderByDesc('id')->first();
    }

    public function test_un_usuario_del_dominio_no_registrado_no_ingresa(): void
    {
        $this->volverDeGoogle([])
            ->assertRedirect('/login')
            ->assertInertiaFlash('toast.mensaje', 'Tu cuenta no tiene acceso al sistema. Pídelo al administrador.');

        $this->assertGuest();
        $fallo = $this->ultimaAuditoria();
        $this->assertSame('sesion.fallida', $fallo->accion);
        $this->assertSame(['email' => 'ana@uni.edu.pe', 'motivo' => 'no_registrado'], json_decode($fallo->valor_nuevo, true));
    }

    public function test_un_usuario_registrado_y_activo_ingresa_y_queda_auditado(): void
    {
        $ana = User::factory()->create(['email' => 'ana@uni.edu.pe'])->assignRole('coordinador');

        // Google puede devolver el correo con otra capitalización que la registrada.
        $this->volverDeGoogle(['email' => 'Ana@Uni.edu.pe'])->assertRedirect('/');

        $this->assertAuthenticatedAs($ana);
        $inicio = $this->ultimaAuditoria();
        $this->assertSame(['sesion.iniciada', $ana->id], [$inicio->accion, $inicio->usuario_id]);
    }

    public function test_un_usuario_desactivado_no_ingresa(): void
    {
        User::factory()->create(['email' => 'ana@uni.edu.pe', 'activo' => false]);

        $this->volverDeGoogle([])->assertRedirect('/login');

        $this->assertGuest();
        $this->assertSame('inactivo', json_decode($this->ultimaAuditoria()->valor_nuevo, true)['motivo']);
    }

    /** Registrado con el mismo correo, pero la cuenta no la administra el Workspace del dominio. */
    #[DataProvider('cuentasFueraDelDominio')]
    public function test_una_cuenta_fuera_del_dominio_no_ingresa_aunque_el_correo_este_registrado(array $cuenta): void
    {
        User::factory()->create(['email' => 'ana@uni.edu.pe']);

        $this->volverDeGoogle($cuenta)
            ->assertRedirect('/login')
            ->assertInertiaFlash('toast.mensaje', 'Ingresa con tu cuenta institucional (@uni.edu.pe).');

        $this->assertGuest();
        $this->assertSame('fuera_del_dominio', json_decode($this->ultimaAuditoria()->valor_nuevo, true)['motivo']);
    }

    public static function cuentasFueraDelDominio(): array
    {
        return [
            'cuenta personal sin hd' => [['hd' => null]],
            'otro workspace' => [['hd' => 'otra.edu.pe']],
            'correo no verificado' => [['email_verified' => false]],
            'correo de otro dominio' => [['email' => 'ana@gmail.com']],
        ];
    }

    public function test_sin_configurar_google_no_hay_ingreso(): void
    {
        config(['tramite.google_dominio' => null]);

        $this->get('/auth/google')->assertNotFound();
        $this->get('/auth/google/callback')->assertNotFound();
    }

    public function test_la_redireccion_preselecciona_el_dominio(): void
    {
        $this->get('/auth/google')->assertRedirectContains('accounts.google.com')->assertRedirectContains('hd=uni.edu.pe');
    }

    public function test_desactivar_corta_la_sesion_abierta(): void
    {
        $ana = User::factory()->create()->assignRole('director');
        $this->actingAs($ana)->get('/')->assertOk();

        $ana->update(['activo' => false]);

        $this->get('/')->assertRedirect('/login');
        $this->assertGuest();
    }
}
