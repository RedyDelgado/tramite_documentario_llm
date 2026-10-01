<?php

namespace App\Http\Resources;

use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Area */
class AreaResource extends JsonResource
{
    /** Forma espejada en resources/js/types/index.ts (Area). */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'palabras_clave' => $this->palabras_clave ?? [],
            'parent_id' => $this->parent_id,
            'padre' => $this->whenLoaded('padre', fn () => $this->padre?->nombre),
            'orden' => $this->orden,
            'activa' => $this->activa,
            'actualizada' => $this->updated_at?->toIso8601String(),
        ];
    }
}
