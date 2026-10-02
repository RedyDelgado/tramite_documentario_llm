<?php

namespace App\Http\Resources;

use App\Models\ReglaDerivacion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReglaDerivacion */
class ReglaDerivacionResource extends JsonResource
{
    /** Forma espejada en resources/js/types/index.ts (ReglaDerivacion). Requiere tipoTramite, areaDestino y responsable cargados. */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'tipo_tramite_id' => $this->tipo_tramite_id,
            'tipo' => $this->tipoTramite?->nombre,
            'palabras_clave' => $this->condicion['palabras_clave'] ?? [],
            'remitentes' => $this->condicion['remitentes'] ?? [],
            'area_destino_id' => $this->area_destino_id,
            'area_destino' => $this->areaDestino->nombre,
            'responsable_id' => $this->responsable_id,
            'responsable' => $this->responsable?->name,
            'prioridad' => $this->prioridad,
            'activa' => $this->activa,
            'actualizado' => $this->updated_at?->toIso8601String(),
        ];
    }
}
