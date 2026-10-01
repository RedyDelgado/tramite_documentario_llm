<?php

namespace App\Http\Requests;

use App\Models\Area;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $area = $this->route('area');

        return $area
            ? $this->user()->can('update', $area)
            : $this->user()->can('create', Area::class);
    }

    public function rules(): array
    {
        return [
            'nombre' => [
                'required', 'string', 'max:150',
                Rule::unique('areas', 'nombre')->ignore($this->route('area'))->whereNull('deleted_at'),
            ],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'palabras_clave' => ['present', 'array', 'max:30'],
            'palabras_clave.*' => ['string', 'distinct', 'max:50'],
            'parent_id' => ['nullable', 'integer', Rule::exists('areas', 'id')->whereNull('deleted_at')],
            'orden' => ['required', 'integer', 'min:0', 'max:9999'],
            'activa' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'parent_id' => 'área superior',
            'palabras_clave' => 'palabras clave',
            'palabras_clave.*' => 'palabra clave',
        ];
    }

    /** La jerarquía no admite ciclos: el área superior no puede ser ella misma ni una descendiente. */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $area = $this->route('area');
                $padreId = $this->integer('parent_id') ?: null;

                if (! $area || ! $padreId) {
                    return;
                }

                $visitados = [];
                while ($padreId && ! in_array($padreId, $visitados, true)) {
                    if ($padreId === $area->id) {
                        $validator->errors()->add('parent_id', 'Un área no puede depender de sí misma ni de una de sus áreas dependientes.');

                        return;
                    }
                    $visitados[] = $padreId;
                    $padreId = Area::whereKey($padreId)->value('parent_id');
                }
            },
        ];
    }
}
