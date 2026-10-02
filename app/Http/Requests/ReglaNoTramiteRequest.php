<?php

namespace App\Http\Requests;

use App\Models\ReglaNoTramite;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReglaNoTramiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $regla = $this->route('regla');

        return $regla ? $this->user()->can('update', $regla) : $this->user()->can('create', ReglaNoTramite::class);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'campo' => ['required', Rule::in(array_keys(ReglaNoTramite::CAMPOS))],
            'valor' => ['required', 'string', 'max:255'],
            'activa' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function datos(): array
    {
        // Las reglas comparan en minúsculas (ReglaNoTramite::aplica).
        return ['valor' => mb_strtolower(trim($this->validated('valor')))] + $this->validated();
    }
}
