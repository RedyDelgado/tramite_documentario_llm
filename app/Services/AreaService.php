<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocio;
use App\Models\Area;
use App\Models\Expediente;
use Illuminate\Support\Facades\DB;

/** Única vía de escritura de áreas; cada cambio queda auditado con su valor anterior (5.1). */
class AreaService
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    /** @param array<string, mixed> $datos */
    public function crear(array $datos): Area
    {
        return DB::transaction(function () use ($datos) {
            $area = Area::create($datos);
            $this->auditoria->registrar('area.creada', $area, despues: $area->only(array_keys($datos)));

            return $area;
        });
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(Area $area, array $datos): Area
    {
        return DB::transaction(function () use ($area, $datos) {
            $area->update($datos);
            $this->auditoria->registrarCambios('area.actualizada', $area);

            return $area;
        });
    }

    /**
     * Pasa al destino los expedientes y las áreas dependientes del origen, y desactiva el origen (5.1).
     * Sus responsables no se trasladan: el destino conserva los suyos.
     *
     * @return int expedientes reasignados
     *
     * @throws ReglaDeNegocio
     */
    public function fusionar(Area $origen, Area $destino): int
    {
        if ($origen->is($destino) || ! $destino->activa || ! $origen->activa) {
            throw new ReglaDeNegocio('Solo se fusiona un área activa en otra área activa distinta.');
        }
        // Fusionar en una dependiente crearía un ciclo en la jerarquía.
        for ($padre = $destino->parent_id; $padre; $padre = Area::whereKey($padre)->value('parent_id')) {
            if ($padre === $origen->id) {
                throw new ReglaDeNegocio("«{$destino->nombre}» depende de «{$origen->nombre}»; no se puede fusionar en ella.");
            }
        }

        $ids = DB::transaction(function () use ($origen, $destino) {
            $ids = Expediente::where('area_principal_id', $origen->id)->lockForUpdate()->pluck('id')->all();
            Expediente::whereKey($ids)->update(['area_principal_id' => $destino->id]);
            $hijas = $origen->hijas()->pluck('id')->all();
            Area::whereKey($hijas)->update(['parent_id' => $destino->id]);
            $origen->update(['activa' => false]);

            $this->auditoria->registrar('area.fusionada', $origen,
                antes: ['activa' => true],
                despues: ['activa' => false, 'destino_id' => $destino->id, 'expedientes' => $ids, 'areas_dependientes' => $hijas],
            );

            return $ids;
        });

        // El índice guarda las áreas que ven cada expediente: se rehace para los movidos.
        Expediente::whereKey($ids)->searchable();

        return count($ids);
    }

    /** Desactivar en lugar de borrar: un área con expedientes nunca se elimina (5.1). */
    public function cambiarEstado(Area $area, bool $activa): Area
    {
        return DB::transaction(function () use ($area, $activa) {
            $area->update(['activa' => $activa]);
            $this->auditoria->registrarCambios($activa ? 'area.activada' : 'area.desactivada', $area);

            return $area;
        });
    }
}
