<?php

namespace Tests\Feature;

use App\Enums\EstadoExpediente;
use App\Mail\ResumenDiario;
use App\Models\Area;
use App\Models\AreaResponsable;
use App\Models\DocumentoSaliente;
use App\Models\Expediente;
use App\Models\TipoDocumento;
use App\Models\User;
use App\Services\PanelService;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PanelYResumenTest extends TestCase
{
    use RefreshDatabase;

    private Area $area;

    private User $coordinador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesSeeder::class);
        $this->travelTo('2026-10-20 09:00:00');
        $this->area = Area::factory()->create(['nombre' => 'Laboratorios']);
        $this->coordinador = User::factory()->create()->assignRole('coordinador');
        AreaResponsable::create(['area_id' => $this->area->id, 'user_id' => $this->coordinador->id, 'tipo' => 'titular', 'vigente_desde' => '2026-01-01']);
    }

    private function expediente(array $datos): Expediente
    {
        return Expediente::factory()->create($datos + ['area_principal_id' => $this->area->id, 'registrado_at' => '2026-10-01 09:00:00', 'fecha_ingreso' => '2026-10-01 09:00:00']);
    }

    public function test_los_indicadores_respetan_lo_que_cada_usuario_puede_ver(): void
    {
        $this->expediente(['estado' => EstadoExpediente::Derivado, 'fecha_limite' => '2026-10-10']);
        // Atendido el 5 con plazo al 10 (en plazo) y otro el 15 con plazo al 10 (fuera de plazo).
        $this->expediente(['estado' => EstadoExpediente::Cerrado, 'fecha_limite' => '2026-10-10', 'atendido_at' => '2026-10-05 10:00:00']);
        $this->expediente(['estado' => EstadoExpediente::Cerrado, 'fecha_limite' => '2026-10-10', 'atendido_at' => '2026-10-15 10:00:00']);
        Expediente::factory()->create(['estado' => EstadoExpediente::Derivado, 'area_principal_id' => Area::factory()->create()->id]);

        $delCoordinador = app(PanelService::class)->indicadores($this->coordinador);
        $delDirector = app(PanelService::class)->indicadores(User::factory()->create()->assignRole('director'));

        $this->assertSame(1, $delCoordinador['abiertos']);
        $this->assertSame(2, $delDirector['abiertos']);
        $this->assertSame(['atendidos' => 2, 'en_plazo' => 1], $delCoordinador['en_plazo']);
        $this->assertSame(1, $delCoordinador['por_semaforo']['rojo']);
        $this->assertSame([['area' => 'Laboratorios', 'responsable' => null, 'abiertos' => 1, 'rojos' => 1]], $delCoordinador['carga']);
        $this->assertSame([['nombre' => 'Laboratorios', 'dias' => 9.0, 'total' => 2]], $delCoordinador['tiempo_por_area']);
    }

    public function test_la_adopcion_cuenta_solo_lo_respondido_desde_el_sistema(): void
    {
        $respondido = function (Expediente $e, string $estado = 'enviado') {
            DocumentoSaliente::create([
                'expediente_id' => $e->id, 'tipo_documento_id' => TipoDocumento::firstOrCreate(['nombre' => 'Oficio'])->id,
                'area_id' => $e->area_principal_id, 'asunto' => 'Respuesta', 'cuerpo' => 'Texto',
                'destinatarios' => [['email' => 'a@b.pe', 'nombre' => null]], 'es_respuesta' => true, 'creado_por' => $this->coordinador->id,
            ])->forceFill(['estado' => $estado])->save();
        };
        $terminado = ['estado' => EstadoExpediente::Atendido, 'requiere_respuesta' => true, 'atendido_at' => '2026-10-05 10:00:00'];

        $respondido($this->expediente($terminado));
        // Cerrado a mano: respondieron por fuera del sistema.
        $this->expediente(['estado' => EstadoExpediente::Cerrado] + $terminado);
        // La respuesta quedó en borrador y lo cerraron igual.
        $respondido($this->expediente(['estado' => EstadoExpediente::Cerrado] + $terminado), 'borrador');
        // Solo para conocimiento: no exigía respuesta.
        $this->expediente(['requiere_respuesta' => false] + $terminado);
        // De otra área: el coordinador no lo ve.
        $respondido(Expediente::factory()->create(['area_principal_id' => Area::factory()->create()->id] + $terminado));

        $this->assertSame(['con_respuesta' => 3, 'desde_sistema' => 1], app(PanelService::class)->indicadores($this->coordinador)['adopcion']);
        $this->assertSame(['con_respuesta' => 4, 'desde_sistema' => 2], app(PanelService::class)->indicadores(User::factory()->create()->assignRole('director'))['adopcion']);
    }

    public function test_la_tendencia_cuenta_cada_tramite_en_su_mes_de_lima(): void
    {
        $this->expediente(['estado' => EstadoExpediente::Derivado, 'fecha_ingreso' => '2026-10-02 09:00:00', 'anio' => 2026, 'secuencia' => 50]);
        // 30 de septiembre a las 22:00 en Lima, aunque en UTC ya sea 1 de octubre.
        $this->expediente(['estado' => EstadoExpediente::Cerrado, 'fecha_ingreso' => '2026-10-01 03:00:00+00', 'atendido_at' => '2026-10-05 10:00:00', 'anio' => 2026, 'secuencia' => 51]);
        // Trámite en curso del papel: llegó en agosto aunque se registró en octubre.
        $this->expediente(['estado' => EstadoExpediente::Derivado, 'fecha_ingreso' => '2026-08-17 00:00:00', 'registrado_at' => '2026-10-03 12:00:00', 'anio' => 2026, 'secuencia' => 12]);
        // Fuera de los 12 meses, de otra área o sin registrar como trámite: no cuentan.
        $this->expediente(['estado' => EstadoExpediente::Derivado, 'fecha_ingreso' => '2025-10-31 09:00:00', 'anio' => 2025, 'secuencia' => 900]);
        Expediente::factory()->create(['estado' => EstadoExpediente::Derivado, 'area_principal_id' => Area::factory()->create()->id, 'fecha_ingreso' => '2026-10-02 09:00:00', 'anio' => 2026, 'secuencia' => 52]);
        $this->expediente(['estado' => EstadoExpediente::NoTramite, 'fecha_ingreso' => '2026-10-02 09:00:00']);

        $tendencia = app(PanelService::class)->indicadores($this->coordinador)['tendencia'];

        $this->assertCount(12, $tendencia);
        $this->assertSame(['mes' => '2025-11', 'ingresados' => 0, 'atendidos' => 0], $tendencia[0]);
        $this->assertSame([
            ['mes' => '2026-08', 'ingresados' => 1, 'atendidos' => 0],
            ['mes' => '2026-09', 'ingresados' => 1, 'atendidos' => 0],
            ['mes' => '2026-10', 'ingresados' => 1, 'atendidos' => 1],
        ], array_slice($tendencia, -3));
    }

    public function test_el_superadmin_no_recibe_indicadores_de_tramites(): void
    {
        $this->actingAs(User::factory()->create()->assignRole('superadmin'))->get('/')
            ->assertInertia(fn ($page) => $page->component('Inicio')->where('indicadores', null));
    }

    public function test_el_resumen_diario_llega_solo_a_coordinadores_con_pendientes_y_queda_auditado(): void
    {
        Mail::fake();
        $vencido = $this->expediente(['estado' => EstadoExpediente::Derivado, 'fecha_limite' => '2026-10-10']);
        $this->expediente(['estado' => EstadoExpediente::Cerrado]);
        User::factory()->create()->assignRole('coordinador');

        $this->artisan('resumen:diario')->assertSuccessful();

        Mail::assertQueued(ResumenDiario::class, 1);
        Mail::assertQueued(ResumenDiario::class, function (ResumenDiario $mail) use ($vencido) {
            $mail->assertSeeInHtml(route('expedientes.show', $vencido));

            return $mail->hasTo($this->coordinador->email) && $mail->expedientes->pluck('id')->all() === [$vencido->id]
                && $mail->envelope()->subject === 'Trámites pendientes: 1 (1 en rojo)';
        });
        $this->assertDatabaseHas('auditoria', ['accion' => 'notificacion.resumen_diario']);
    }
}
