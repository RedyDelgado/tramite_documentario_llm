<?php

namespace App\Services;

use App\Enums\EstadoExpediente;
use App\Models\Expediente;
use App\Models\Grupo;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Series de documentos relacionados (7.3.1, 7.3.5): agrupar, quitar y derivar el lote una sola vez. */
class SerieService
{
    public function __construct(private readonly AuditoriaService $auditoria, private readonly AtencionService $atencion) {}

    /**
     * Agrupa los expedientes en una serie existente o en una nueva con ese nombre.
     *
     * @param  list<int>  $ids
     */
    public function agrupar(array $ids, ?Grupo $grupo, ?string $nombre, User $user): Grupo
    {
        return DB::transaction(function () use ($ids, $grupo, $nombre, $user) {
            $grupo ??= Grupo::create(['nombre' => $nombre, 'creado_por' => $user->id]);

            Expediente::whereKey($ids)->visiblesPara($user)->get()->each(function (Expediente $e) use ($grupo) {
                $antes = $e->grupo_id;
                $e->forceFill(['grupo_id' => $grupo->id])->save();
                $this->auditoria->registrar('expediente.agrupado', $e, antes: ['grupo_id' => $antes], despues: ['grupo_id' => $grupo->id, 'serie' => $grupo->nombre]);
            });

            return $grupo;
        });
    }

    public function quitar(Expediente $expediente): void
    {
        $antes = $expediente->grupo_id;
        $expediente->forceFill(['grupo_id' => null])->save();
        $this->auditoria->registrar('expediente.desagrupado', $expediente, antes: ['grupo_id' => $antes], despues: ['grupo_id' => null]);
    }

    /**
     * Deriva todos los de la serie que se pueden derivar, con los mismos datos; cada uno conserva su registro y su auditoría.
     *
     * @param  array<string, mixed>  $datos
     * @return int expedientes derivados
     */
    public function derivar(Grupo $grupo, array $datos, User $user): int
    {
        $derivables = [EstadoExpediente::Registrado, EstadoExpediente::Derivado, EstadoExpediente::EnAtencion];

        return DB::transaction(fn () => $grupo->expedientes()->whereIn('estado', $derivables)->orderBy('id')->get()
            ->filter(fn (Expediente $e) => $user->can('derivar', $e))
            ->each(fn (Expediente $e) => $this->atencion->derivar($e, $datos))
            ->count());
    }

    /**
     * Otros documentos del mismo emisor (o remitente), mismo asunto y mismo día, sin serie: candidatos a agruparse (7.3.1).
     *
     * @return Collection<int, Expediente>
     */
    public function parecidos(Expediente $e, User $user): Collection
    {
        if ($e->grupo_id || ! ($e->emisor_id || $e->remitente_email)) {
            return collect();
        }

        return Expediente::visiblesPara($user)
            ->whereKeyNot($e->id)
            ->whereNull('grupo_id')
            ->whereNotIn('estado', [EstadoExpediente::Anulado, EstadoExpediente::NoTramite])
            ->when($e->emisor_id, fn ($q) => $q->where('emisor_id', $e->emisor_id), fn ($q) => $q->where('remitente_email', $e->remitente_email))
            ->whereRaw('lower(asunto) = lower(?)', [$e->asunto])
            ->whereRaw("(fecha_ingreso AT TIME ZONE 'America/Lima')::date = ?", [$e->fecha_ingreso->setTimezone(config('app.timezone'))->toDateString()])
            ->orderBy('id')
            ->limit(50)
            ->get();
    }
}
