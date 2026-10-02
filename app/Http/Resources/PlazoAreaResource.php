<?php

namespace App\Http\Resources;

use App\Models\PlazoArea;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PlazoArea */
class PlazoAreaResource extends JsonResource
{
    /** Forma espejada en resources/js/types/index.ts (PlazoArea). Requiere tipoTramite y area cargados. */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo_tramite_id' => $this->tipo_tramite_id,
            'tipo' => $this->tipoTramite->nombre,
            'tipo_dias' => $this->tipoTramite->tipo_dias,
            'plazo_del_tipo' => $this->tipoTramite->plazo_dias,
            'area_id' => $this->area_id,
            'area' => $this->area->nombre,
            'plazo_dias' => $this->plazo_dias,
            'actualizado' => $this->updated_at?->toIso8601String(),
        ];
    }
}
