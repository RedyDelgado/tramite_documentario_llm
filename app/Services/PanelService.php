<?php

namespace App\Services;

use App\Enums\EstadoExpediente;
use App\Enums\Semaforo;
use App\Models\Configuracion;
use App\Models\DocumentoSaliente;
use App\Models\Expediente;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/** Indicadores del panel (8), siempre sobre lo que el usuario puede ver. */
class PanelService
{
    private const ABIERTOS = [EstadoExpediente::Derivado, EstadoExpediente::EnAtencion];

    private const TERMINADOS = [EstadoExpediente::Atendido, EstadoExpediente::Cerrado];

    /** @return array<string, mixed> */
    public function indicadores(User $user): array
    {
        $base = fn (): Builder => Expediente::visiblesPara($user);
        $anio = now()->startOfYear();
        $diasQuieto = (int) Configuracion::valor('semaforo.dias_sin_movimiento');

        $terminados = $base()->whereIn('estado', self::TERMINADOS)->where('atendido_at', '>=', $anio);
        $conPlazo = (clone $terminados)->whereNotNull('fecha_limite');
        // atendido_at es un instante; se compara su día en Lima con la fecha límite.
        $enPlazo = (clone $conPlazo)->whereRaw("(atendido_at AT TIME ZONE 'America/Lima')::date <= fecha_limite")->count();
        // Adopción del piloto (16.7): lo que exigía respuesta y la tuvo enviada desde el sistema, no cerrado a mano.
        $conRespuesta = (clone $terminados)->where('requiere_respuesta', true);
        $desdeSistema = (clone $conRespuesta)
            ->whereIn('expedientes.id', DocumentoSaliente::where('es_respuesta', true)->where('estado', 'enviado')->select('expediente_id'))
            ->count();

        return [
            'por_estado' => $this->contar($base(), 'estado', fn ($v) => EstadoExpediente::from($v)->etiqueta()),
            'por_semaforo' => collect(Semaforo::cases())->mapWithKeys(fn (Semaforo $s) => [$s->value => 0])
                ->merge($base()->toBase()->whereNotNull('semaforo')->groupBy('semaforo')->selectRaw('semaforo, count(*) as total')->pluck('total', 'semaforo')->map(fn ($n) => (int) $n))
                ->all(),
            'abiertos' => $base()->whereIn('estado', self::ABIERTOS)->count(),
            'por_revisar' => $base()->where('estado', EstadoExpediente::PorRevisar)->count(),
            'sin_movimiento' => $base()->whereIn('estado', self::ABIERTOS)->where('ultimo_movimiento_at', '<', now()->subDays($diasQuieto))->count(),
            'dias_sin_movimiento' => $diasQuieto,
            'en_plazo' => ['atendidos' => $conPlazo->count(), 'en_plazo' => $enPlazo],
            'adopcion' => ['con_respuesta' => $conRespuesta->count(), 'desde_sistema' => $desdeSistema],
            'tiempo_por_area' => $this->tiempoPromedio($terminados, 'areas', 'area_principal_id'),
            'tiempo_por_tipo' => $this->tiempoPromedio($terminados, 'tipos_tramite', 'tipo_tramite_id'),
            'carga' => $this->carga($base()),
        ];
    }

    /** @return list<array{valor: string, etiqueta: string, total: int}> */
    private function contar(Builder $query, string $columna, callable $etiqueta): array
    {
        return $query->groupBy($columna)->orderByDesc(DB::raw('count(*)'))->get([$columna, DB::raw('count(*) as total')])
            ->map(fn ($f) => ['valor' => $f->getRawOriginal($columna), 'etiqueta' => $etiqueta($f->getRawOriginal($columna)), 'total' => (int) $f->total])
            ->all();
    }

    /**
     * Días promedio entre el registro y la atención, agrupados por área o tipo.
     *
     * @return list<array{nombre: string, dias: float, total: int}>
     */
    private function tiempoPromedio(Builder $terminados, string $tabla, string $columna): array
    {
        return (clone $terminados)->join($tabla, "{$tabla}.id", '=', "expedientes.{$columna}")
            ->whereNotNull('registrado_at')
            ->groupBy("{$tabla}.nombre")
            ->orderBy("{$tabla}.nombre")
            ->get([
                "{$tabla}.nombre",
                DB::raw('round(avg(extract(epoch from (atendido_at - registrado_at)) / 86400)::numeric, 1) as dias'),
                DB::raw('count(*) as total'),
            ])
            ->map(fn ($f) => ['nombre' => $f->nombre, 'dias' => (float) $f->dias, 'total' => (int) $f->total])
            ->all();
    }

    /**
     * Abiertos por área y responsable directo, con cuántos están en rojo.
     *
     * @return list<array{area: string, responsable: ?string, abiertos: int, rojos: int}>
     */
    private function carga(Builder $base): array
    {
        return $base->whereIn('estado', self::ABIERTOS)
            ->join('areas', 'areas.id', '=', 'expedientes.area_principal_id')
            ->leftJoin('users', 'users.id', '=', 'expedientes.responsable_id')
            ->groupBy('areas.nombre', 'users.name')
            ->orderByDesc(DB::raw('count(*)'))
            ->get([
                'areas.nombre as area',
                'users.name as responsable',
                DB::raw('count(*) as abiertos'),
                DB::raw("count(*) filter (where expedientes.semaforo = 'rojo') as rojos"),
            ])
            ->map(fn ($f) => ['area' => $f->area, 'responsable' => $f->responsable, 'abiertos' => (int) $f->abiertos, 'rojos' => (int) $f->rojos])
            ->all();
    }
}
