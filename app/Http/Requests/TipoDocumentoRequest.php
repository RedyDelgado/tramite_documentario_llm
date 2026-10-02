<?php

namespace App\Http\Requests;

use App\Models\TipoDocumento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TipoDocumentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tipo = $this->route('tipo');

        return $tipo ? $this->user()->can('update', $tipo) : $this->user()->can('create', TipoDocumento::class);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100', Rule::unique('tipos_documento', 'nombre')->ignore($this->route('tipo'))],
            // Numeración de lo emitido (7.3.4): {NUMERO} es obligatorio para que el correlativo se vea.
            'formato_numero' => ['required', 'string', 'max:100', 'regex:/\{NUMERO\}/'],
            'aprueba_salida' => ['required', Rule::in(array_keys(TipoDocumento::APRUEBA_SALIDA))],
            'activo' => ['required', 'boolean'],
        ];
    }
}
