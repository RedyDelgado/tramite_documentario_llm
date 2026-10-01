<?php

namespace App\Services;

use App\Models\Area;
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
