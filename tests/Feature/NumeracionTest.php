<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Models\Area;
use App\Models\DocumentoSaliente;
use App\Models\Expediente;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Services\ExpedienteService;
use App\Services\RegistroFisicoService;
use App\Services\SalienteService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class NumeracionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        Storage::fake('originales');
        Queue::fake();
        $this->travelTo('2026-10-08 10:00:00');
        $this->admin = User::factory()->create()->assignRole('superadmin');
    }

    private function ajustar(array $datos, ?User $quien = null): TestResponse
    {
        return $this->actingAs($quien ?? $this->admin)->post('/numeracion', $datos + ['tipo' => 'registro', 'anio' => 2026]);
    }

    private function registrar(): Expediente
    {
        return app(ExpedienteService::class)->confirmar(Expediente::factory()->create(['estado' => EstadoExpediente::PorRevisar]));
    }

    public function test_el_registro_continua_con_el_numero_que_fija_el_administrador(): void
    {
        $this->ajustar(['siguiente' => 120])->assertSessionHasNoErrors()->assertRedirect('/numeracion?anio=2026');

        $this->actingAs($this->admin)->get('/numeracion')
            ->assertInertia(fn ($page) => $page->where('correlativos.0.siguiente', 120)->where('correlativos.0.ejemplo', 'N°00120'));
        $this->assertSame('N°00120', $this->registrar()->numero_registro);
        $this->assertSame('N°00121', $this->registrar()->numero_registro);
        // Lo anterior al primer número del sistema es del registro en papel: los trámites en curso van del 1 al 119.
        $this->assertSame(119, RegistroFisicoService::ultimoNumeroEnPapel());

        $ajuste = DB::table('auditoria')->where('accion', 'numeracion.ajustada')->sole();
        $this->assertSame(['siguiente' => 38], json_decode($ajuste->valor_anterior, true));
        $this->assertSame(['siguiente' => 120], json_decode($ajuste->valor_nuevo, true));
    }

    public function test_no_se_vuelve_a_un_numero_ya_usado_y_saltar_no_cambia_el_rango_del_papel(): void
    {
        $this->assertSame('N°00038', $this->registrar()->numero_registro);

        $this->ajustar(['siguiente' => 38])->assertSessionHasErrors(['siguiente' => 'Ya se usó el número 38 en 2026: el siguiente debe ser mayor.']);
        $this->ajustar(['siguiente' => 50])->assertSessionHasNoErrors();

        $this->assertSame('N°00050', $this->registrar()->numero_registro);
        $this->assertSame(37, RegistroFisicoService::ultimoNumeroEnPapel());
    }

    public function test_un_documento_emitido_continua_el_correlativo_de_su_tipo_y_area(): void
    {
        $oficio = TipoDocumento::create(['nombre' => 'Oficio']);
        $area = Area::factory()->create(['siglas' => 'DGA']);
        $this->ajustar(['tipo' => 'saliente', 'tipo_documento_id' => $oficio->id, 'area_id' => $area->id, 'siguiente' => 45])->assertSessionHasNoErrors();

        $saliente = DocumentoSaliente::create([
            'tipo_documento_id' => $oficio->id, 'area_id' => $area->id, 'asunto' => 'Respuesta', 'cuerpo' => 'Texto',
            'destinatarios' => [['email' => 'mesa@muni.gob.pe', 'nombre' => null]], 'es_respuesta' => false, 'creado_por' => User::factory()->create()->id,
        ]);
        $saliente->forceFill(['estado' => 'en_revision'])->save();
        $aprobado = app(SalienteService::class)->aprobar($saliente, User::factory()->create()->assignRole('director'));

        $this->assertSame('OFICIO N.º 045-2026-DGA', $aprobado->numero);
        $this->actingAs($this->admin)->get('/numeracion')
            ->assertInertia(fn ($page) => $page->where('correlativos.1.nombre', "Oficio · {$area->nombre}")->where('correlativos.1.ultimo_usado', 45)->where('correlativos.1.ejemplo', 'OFICIO N.º 046-2026-DGA'));
    }

    public function test_solo_quien_administra_la_configuracion_ajusta_la_numeracion(): void
    {
        $director = User::factory()->create()->assignRole('director');

        $this->actingAs($director)->get('/numeracion')->assertForbidden();
        $this->ajustar(['siguiente' => 120], $director)->assertForbidden();
        $this->ajustar(['siguiente' => 120, 'anio' => 2025])->assertSessionHasErrors('anio');
        $this->assertSame(0, DB::table('auditoria')->where('accion', 'numeracion.ajustada')->count());

        $director->givePermissionTo('configuracion.gestionar');
        $this->actingAs($director->fresh())->get('/numeracion')->assertOk();
    }
}
