<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DerivarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('derivar', $this->route('expediente'));
    }

    public function rules(): array
    {
        return [
            'tipo_tramite_id' => ['required', 'integer', Rule::exists('tipos_tramite', 'id')->where('activo', true)],
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')->where('activa', true)->whereNull('deleted_at')],
            'responsable_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('activo', true)],
            'requiere_respuesta' => ['required', 'boolean'],
            'instruccion' => ['nullable', 'string', 'max:200'],
            'nota' => ['nullable', 'string', 'max:2000'],
            // Fecha que fija el documento (reunión, entrega); vacía, rige el plazo del tipo (8).
            'fecha_limite' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today'],
            // Una serie se deriva una sola vez para todo el lote (7.3.5, punto 6).
            'toda_la_serie' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tipo_tramite_id' => 'tipo de trámite',
            'area_id' => 'área',
            'responsable_id' => 'responsable',
            'requiere_respuesta' => 'requiere respuesta',
            'instruccion' => 'instrucción',
            'fecha_limite' => 'fecha límite',
        ];
    }
}
