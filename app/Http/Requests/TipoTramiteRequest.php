<?php

namespace App\Http\Requests;

use App\Models\TipoTramite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TipoTramiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tipo = $this->route('tipo');

        return $tipo ? $this->user()->can('update', $tipo) : $this->user()->can('create', TipoTramite::class);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150', Rule::unique('tipos_tramite', 'nombre')->ignore($this->route('tipo'))],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'plazo_dias' => ['nullable', 'integer', 'min:1', 'max:365'],
            'tipo_dias' => ['required', Rule::in(array_keys(TipoTramite::TIPOS_DIAS))],
            'aprueba_cierre' => ['nullable', Rule::in(array_keys(TipoTramite::APRUEBA_CIERRE))],
            'activo' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['plazo_dias' => 'plazo', 'tipo_dias' => 'tipo de días', 'aprueba_cierre' => 'aprobación del cierre'];
    }
}
