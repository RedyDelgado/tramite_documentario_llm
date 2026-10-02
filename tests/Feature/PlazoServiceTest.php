<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Feriado;
use App\Models\PlazoArea;
use App\Models\TipoTramite;
use App\Services\PlazoService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlazoServiceTest extends TestCase
{
    use RefreshDatabase;

    // Viernes.
    private const INGRESO = '2026-10-02 09:00:00';

    private function limite(TipoTramite $tipo, ?int $areaId = null, string $desde = self::INGRESO): ?string
    {
        return app(PlazoService::class)->calcular($tipo, $areaId, CarbonImmutable::parse($desde))['fecha_limite']?->toDateString();
    }

    private function tipo(?int $dias, string $tipoDias = 'habiles'): TipoTramite
    {
        return TipoTramite::create(['nombre' => "Tipo {$dias} {$tipoDias}", 'plazo_dias' => $dias, 'tipo_dias' => $tipoDias]);
    }

    public function test_dias_habiles_saltan_el_fin_de_semana_y_no_cuentan_el_dia_de_ingreso(): void
    {
        $this->assertSame('2026-10-07', $this->limite($this->tipo(3)));
    }

    public function test_feriados_globales_y_del_area_no_cuentan_y_los_quitados_si(): void
    {
        $tipo = $this->tipo(3);
        $filial = Area::factory()->create();
        Feriado::create(['fecha' => '2026-10-06', 'descripcion' => 'Feriado nacional']);
        Feriado::create(['fecha' => '2026-10-07', 'descripcion' => 'Aniversario de la filial', 'area_id' => $filial->id]);
        Feriado::create(['fecha' => '2026-10-05', 'descripcion' => 'Registrado por error'])->delete();

        $this->assertSame('2026-10-08', $this->limite($tipo));
        $this->assertSame('2026-10-09', $this->limite($tipo, $filial->id));
    }

    public function test_dias_calendario_que_vencen_en_inhabil_pasan_al_siguiente_habil(): void
    {
        $this->assertSame('2026-10-07', $this->limite($this->tipo(5, 'calendario')));
        // Viernes + 8 = sábado 10 → lunes 12.
        $this->assertSame('2026-10-12', $this->limite($this->tipo(8, 'calendario')));
    }

    public function test_el_plazo_del_area_manda_sobre_el_del_tipo(): void
    {
        $tipo = $this->tipo(3);
        $area = Area::factory()->create();
        PlazoArea::create(['tipo_tramite_id' => $tipo->id, 'area_id' => $area->id, 'plazo_dias' => 1]);

        $this->assertSame('2026-10-05', $this->limite($tipo, $area->id));
        $this->assertSame('2026-10-07', $this->limite($tipo));
    }

    public function test_sin_plazo_no_hay_fecha_limite(): void
    {
        $this->assertNull($this->limite($this->tipo(null)));
    }

    public function test_el_dia_de_ingreso_es_el_de_lima_y_no_el_de_utc(): void
    {
        // 03:00 UTC del viernes es 22:00 del jueves en Lima: el plazo corre desde el viernes.
        $this->assertSame('2026-10-02', $this->limite($this->tipo(1), desde: '2026-10-02T03:00:00Z'));
    }
}
