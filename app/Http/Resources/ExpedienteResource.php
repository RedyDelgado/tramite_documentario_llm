<?php

namespace App\Http\Resources;

use App\Models\Expediente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Expediente */
class ExpedienteResource extends JsonResource
{
    /** Forma espejada en resources/js/types/index.ts (ExpedienteFila). */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'numero_registro' => $this->numero_registro,
            'codigo' => $this->codigo,
            'asunto' => $this->asunto,
            'remitente_nombre' => $this->remitente_nombre,
            'remitente_email' => $this->remitente_email,
            'remitente_por_confirmar' => $this->remitente_por_confirmar,
            'estado' => ['valor' => $this->estado->value, 'etiqueta' => $this->estado->etiqueta()],
            'semaforo' => $this->semaforo?->value,
            'fecha_limite' => $this->fecha_limite?->toDateString(),
            'fecha_ingreso' => $this->fecha_ingreso->toIso8601String(),
            'area' => $this->whenLoaded('area', fn () => $this->area?->nombre),
            'documentos_count' => $this->whenCounted('documentos'),
            // La fila ya pasó por visiblesPara; basta el permiso (evita una consulta por fila).
            'puede_registrar' => $request->user()->can('expedientes.registrar'),
        ];
    }
}
