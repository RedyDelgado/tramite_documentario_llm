<?php

namespace App\Http\Resources;

use App\Models\TipoTramite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TipoTramite */
class TipoTramiteResource extends JsonResource
{
    /** Forma espejada en resources/js/types/index.ts (TipoTramite). */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'plazo_dias' => $this->plazo_dias,
            'tipo_dias' => $this->tipo_dias,
            'aprueba_cierre' => $this->aprueba_cierre,
            'aprueba_cierre_etiqueta' => TipoTramite::APRUEBA_CIERRE[$this->aprueba_cierre] ?? null,
            'activo' => $this->activo,
            'actualizado' => $this->updated_at?->toIso8601String(),
        ];
    }
}
