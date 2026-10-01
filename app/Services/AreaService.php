<?php

namespace App\Services;

use App\Models\Area;

/** Única vía de escritura de áreas: la auditoría de la fase 1 se engancha aquí. */
class AreaService
{
    /** @param array<string, mixed> $datos */
    public function crear(array $datos): Area
    {
        return Area::create($datos);
    }

    /** @param array<string, mixed> $datos */
    public function actualizar(Area $area, array $datos): Area
    {
        $area->update($datos);

        return $area;
    }

    /** Desactivar en lugar de borrar: un área con expedientes nunca se elimina (5.1). */
    public function cambiarEstado(Area $area, bool $activa): Area
    {
        $area->update(['activa' => $activa]);

        return $area;
    }
}
