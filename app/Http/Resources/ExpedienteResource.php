<?php

namespace App\Http\Resources;

use App\Models\Expediente;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

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
            'institucion' => $this->institucion?->nombre,
            'remitente_por_confirmar' => $this->remitente_por_confirmar,
            'estado' => ['valor' => $this->estado->value, 'etiqueta' => $this->estado->etiqueta()],
            'semaforo' => $this->semaforo?->value,
            'fecha_limite' => $this->fecha_limite?->toDateString(),
            'fecha_ingreso' => $this->fecha_ingreso->toIso8601String(),
            // La principal y cuántas más lo atienden: «Dirección +2».
            'area' => $this->whenLoaded('area', fn () => $this->area?->nombre.(($this->atenciones_count ?? 1) > 1 ? ' +'.($this->atenciones_count - 1) : '')),
            'documentos_count' => $this->whenCounted('documentos'),
            'respuesta' => $this->respondido_at ? ['fecha' => Carbon::parse($this->respondido_at)->toIso8601String(), 'nueva' => $this->respuestaNueva()] : null,
            // La fila ya pasó por visiblesPara; basta el permiso (evita una consulta por fila).
            'puede_registrar' => $request->user()->can('expedientes.registrar'),
        ];
    }
}
