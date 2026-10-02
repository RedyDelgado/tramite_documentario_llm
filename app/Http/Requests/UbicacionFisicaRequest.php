<?php

namespace App\Http\Requests;

use App\Models\UbicacionFisica;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UbicacionFisicaRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ubicacion = $this->route('ubicacion');

        return $ubicacion ? $this->user()->can('update', $ubicacion) : $this->user()->can('create', UbicacionFisica::class);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150', Rule::unique('ubicaciones_fisicas', 'nombre')->ignore($this->route('ubicacion'))],
            'descripcion' => ['nullable', 'string', 'max:300'],
            'activa' => ['required', 'boolean'],
        ];
    }
}
