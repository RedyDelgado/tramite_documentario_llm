<?php

namespace App\Http\Resources;

use App\Models\Feriado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Feriado */
class FeriadoResource extends JsonResource
{
    /** Forma espejada en resources/js/types/index.ts (Feriado). */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fecha' => $this->fecha->toDateString(),
            'descripcion' => $this->descripcion,
            'area_id' => $this->area_id,
            'area' => $this->whenLoaded('area', fn () => $this->area?->nombre),
            'actualizado' => $this->updated_at?->toIso8601String(),
        ];
    }
}
