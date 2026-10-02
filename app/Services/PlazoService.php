<?php

namespace App\Services;

use App\Models\Feriado;
use App\Models\PlazoArea;
use App\Models\TipoTramite;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** Plazo aplicable y fecha límite según la configuración del panel (5.1, 8); reglas deterministas. */
class PlazoService
{
    /**
     * El plazo del área manda sobre el del tipo; sin plazo no hay fecha límite.
     *
     * @return array{plazo_dias: ?int, fecha_limite: ?CarbonImmutable}
     */
    public function calcular(TipoTramite $tipo, ?int $areaId, CarbonInterface $desde): array
    {
        $plazo = $areaId
            ? PlazoArea::where('tipo_tramite_id', $tipo->id)->where('area_id', $areaId)->value('plazo_dias') ?? $tipo->plazo_dias
            : $tipo->plazo_dias;

        if ($plazo === null) {
            return ['plazo_dias' => null, 'fecha_limite' => null];
        }

        return ['plazo_dias' => $plazo, 'fecha_limite' => $this->sumar($desde, $plazo, $areaId, $tipo->tipo_dias === 'habiles')];
    }

    /** Fecha límite a N días hábiles (p. ej. la respuesta que exige un documento emitido, 7.3.4). */
    public function sumarDiasHabiles(CarbonInterface $desde, int $dias, ?int $areaId): CarbonImmutable
    {
        return $this->sumar($desde, $dias, $areaId, true);
    }

    private function sumar(CarbonInterface $desde, int $dias, ?int $areaId, bool $habiles): CarbonImmutable
    {
        $dia = CarbonImmutable::instance($desde)->setTimezone(config('app.timezone'))->startOfDay();
        $feriados = Feriado::where('fecha', '>', $dia->toDateString())
            ->where(fn ($q) => $q->whereNull('area_id')->when($areaId, fn ($q) => $q->orWhere('area_id', $areaId)))
            ->pluck('fecha')
            ->mapWithKeys(fn ($fecha) => [substr((string) $fecha, 0, 10) => true]);
        $habil = fn (CarbonImmutable $d) => ! $d->isWeekend() && ! $feriados->has($d->toDateString());

        if (! $habiles) {
            $dia = $dia->addDays($dias);
        } else {
            // El día de inicio no cuenta: el plazo corre desde el día hábil siguiente.
            for ($contados = 0; $contados < $dias;) {
                $dia = $dia->addDay();
                $contados += $habil($dia) ? 1 : 0;
            }
        }

        // Si vence en día inhábil, se prorroga al primer día hábil siguiente (TUO Ley 27444).
        while (! $habil($dia)) {
            $dia = $dia->addDay();
        }

        return $dia;
    }
}
