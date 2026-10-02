<?php

namespace App\Http\Requests;

use App\Models\PlazoArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlazoAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $plazo = $this->route('plazo');

        return $plazo ? $this->user()->can('update', $plazo) : $this->user()->can('create', PlazoArea::class);
    }

    public function rules(): array
    {
        return [
            'tipo_tramite_id' => ['required', 'integer', Rule::exists('tipos_tramite', 'id')],
            'area_id' => [
                'required', 'integer', Rule::exists('areas', 'id')->whereNull('deleted_at'),
                Rule::unique('plazos_area', 'area_id')
                    ->where('tipo_tramite_id', $this->integer('tipo_tramite_id'))
                    ->whereNull('deleted_at')
                    ->ignore($this->route('plazo')),
            ],
            'plazo_dias' => ['required', 'integer', 'min:1', 'max:365'],
        ];
    }

    public function attributes(): array
    {
        return ['tipo_tramite_id' => 'tipo de trámite', 'area_id' => 'área', 'plazo_dias' => 'plazo'];
    }

    public function messages(): array
    {
        return ['area_id.unique' => 'Esta área ya tiene un plazo propio para ese tipo de trámite; edítalo.'];
    }
}
