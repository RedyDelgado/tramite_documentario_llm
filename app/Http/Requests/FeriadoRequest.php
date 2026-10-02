<?php

namespace App\Http\Requests;

use App\Models\Feriado;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FeriadoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $feriado = $this->route('feriado');

        return $feriado ? $this->user()->can('update', $feriado) : $this->user()->can('create', Feriado::class);
    }

    public function rules(): array
    {
        $areaId = $this->integer('area_id') ?: null;

        return [
            'fecha' => [
                'required', 'date_format:Y-m-d',
                Rule::unique('feriados', 'fecha')
                    ->where(fn ($q) => $areaId ? $q->where('area_id', $areaId) : $q->whereNull('area_id'))
                    ->whereNull('deleted_at')
                    ->ignore($this->route('feriado')),
            ],
            'descripcion' => ['required', 'string', 'max:150'],
            'area_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->whereNull('deleted_at')],
        ];
    }

    public function attributes(): array
    {
        return ['area_id' => 'área'];
    }

    public function messages(): array
    {
        return ['fecha.unique' => 'Ese día ya está registrado como feriado para el mismo alcance.'];
    }
}
