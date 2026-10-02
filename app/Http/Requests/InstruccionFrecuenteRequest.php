<?php

namespace App\Http\Requests;

use App\Models\InstruccionFrecuente;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InstruccionFrecuenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $instruccion = $this->route('instruccion');

        return $instruccion ? $this->user()->can('update', $instruccion) : $this->user()->can('create', InstruccionFrecuente::class);
    }

    public function rules(): array
    {
        return [
            'texto' => ['required', 'string', 'max:200', Rule::unique('instrucciones_frecuentes', 'texto')->ignore($this->route('instruccion'))],
            'orden' => ['required', 'integer', 'min:0', 'max:9999'],
            'activa' => ['required', 'boolean'],
        ];
    }
}
