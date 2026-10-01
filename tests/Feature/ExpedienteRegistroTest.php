<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Exceptions\ReglaDeNegocio;
use App\Models\Auditoria;
use App\Models\Expediente;
use App\Services\ExpedienteService;
use App\Services\SecuenciaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

class ExpedienteRegistroTest extends TestCase
{
    use RefreshDatabase;

    private ExpedienteService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 2));
        $this->servicio = app(ExpedienteService::class);
    }

    public function test_2026_continua_el_registro_en_papel_desde_el_38(): void
    {
        $primero = $this->servicio->confirmar(Expediente::factory()->create());
        $segundo = $this->servicio->confirmar(Expediente::factory()->create());

        $this->assertSame('N°00038', $primero->numero_registro);
        $this->assertSame('REG-2026-00038', $primero->codigo);
        $this->assertSame('N°00039', $segundo->numero_registro);
        $this->assertSame(EstadoExpediente::Registrado, $primero->estado);
    }

    public function test_el_numero_reinicia_cada_anio(): void
    {
        $this->travelTo(now()->setDate(2027, 1, 5));

        $this->assertSame('REG-2027-00001', $this->servicio->confirmar(Expediente::factory()->create())->codigo);
    }

    public function test_no_tramite_e_historico_no_consumen_numero(): void
    {
        $this->servicio->marcarNoTramite(Expediente::factory()->create());
        Expediente::factory()->create(['estado' => EstadoExpediente::Historico]);

        $this->assertSame(0, Expediente::whereNotNull('secuencia')->count());
        $this->assertSame('N°00038', $this->servicio->confirmar(Expediente::factory()->create())->numero_registro);
    }

    public function test_un_historico_recibe_numero_solo_al_promoverse(): void
    {
        $historico = Expediente::factory()->create(['estado' => EstadoExpediente::Historico]);

        $this->assertSame('N°00038', $this->servicio->confirmar($historico)->numero_registro);
    }

    public function test_anular_conserva_el_numero_y_no_se_reutiliza(): void
    {
        $erroneo = $this->servicio->confirmar(Expediente::factory()->create());
        $this->servicio->anular($erroneo, 'Registrado dos veces.');
        $siguiente = $this->servicio->confirmar(Expediente::factory()->create());

        $this->assertSame(EstadoExpediente::Anulado, $erroneo->fresh()->estado);
        $this->assertSame('N°00038', $erroneo->fresh()->numero_registro);
        $this->assertSame('N°00039', $siguiente->numero_registro);
        $this->assertSame(['estado' => 'anulado', 'motivo' => 'Registrado dos veces.', 'numero' => 'N°00038'], Auditoria::where('accion', 'registro.anulado')->sole()->valor_nuevo);
    }

    public function test_no_se_confirma_dos_veces_ni_se_anula_sin_numero(): void
    {
        $registrado = $this->servicio->confirmar(Expediente::factory()->create());

        $this->assertRechaza(fn () => $this->servicio->confirmar($registrado));
        $this->assertRechaza(fn () => $this->servicio->anular(Expediente::factory()->create(), 'motivo'));
        $this->assertRechaza(fn () => $this->servicio->marcarNoTramite($registrado));
        $this->assertSame(1, Expediente::whereNotNull('secuencia')->count());
    }

    public function test_un_rollback_devuelve_el_numero(): void
    {
        try {
            DB::transaction(function () {
                app(SecuenciaService::class)->siguiente('registro', 2026);
                throw new \RuntimeException('falla después de tomar número');
            });
        } catch (\RuntimeException) {
        }

        $this->assertSame('N°00038', $this->servicio->confirmar(Expediente::factory()->create())->numero_registro);
    }

    public function test_la_secuencia_exige_transaccion(): void
    {
        DB::rollBack(); // sale de la transacción de RefreshDatabase solo para esta comprobación
        try {
            $this->expectException(LogicException::class);
            app(SecuenciaService::class)->siguiente('registro', 2026);
        } finally {
            DB::beginTransaction();
        }
    }

    public function test_el_codigo_en_un_texto_encuentra_su_expediente(): void
    {
        $expediente = $this->servicio->confirmar(Expediente::factory()->create());

        $this->assertTrue($expediente->is(Expediente::porCodigoEn('RE: Respuesta [REG-2026-00038] informe')));
        $this->assertNull(Expediente::porCodigoEn('RE: sin código'));
    }

    private function assertRechaza(callable $operacion): void
    {
        try {
            $operacion();
            $this->fail('Se esperaba una ReglaDeNegocio.');
        } catch (ReglaDeNegocio) {
            $this->addToAssertionCount(1);
        }
    }
}
