<?php

namespace App\Http\Resources;

use App\Models\AreaResponsable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AreaResponsable */
class ResponsableResource extends JsonResource
{
    /** Forma espejada en resources/js/types/index.ts (Responsable). Requiere area y user cargados. */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'area_id' => $this->area_id,
            'area' => $this->area->nombre,
            'user_id' => $this->user_id,
            'usuario' => $this->user->name,
            'email' => $this->user->email,
            'tipo' => $this->tipo,
            'vigente_desde' => $this->vigente_desde->toDateString(),
            'vigente_hasta' => $this->vigente_hasta?->toDateString(),
            'vigente' => $this->esVigente(),
            'actualizado' => $this->updated_at?->toIso8601String(),
        ];
    }
}
