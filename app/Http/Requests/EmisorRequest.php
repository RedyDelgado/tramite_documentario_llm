<?php

namespace App\Http\Requests;

use App\Models\Emisor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Alta y edición de emisores, también el alta en línea desde el registro (7.3.5). */
class EmisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $emisor = $this->route('emisor');

        return $emisor ? $this->user()->can('update', $emisor) : $this->user()->can('create', Emisor::class);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:200'],
            'tipo' => ['required', Rule::in(array_keys(Emisor::TIPOS))],
            'activo' => ['sometimes', 'boolean'],
            // Alta en línea: confirma que no es ninguno de los parecidos propuestos.
            'confirmado' => ['sometimes', 'boolean'],
        ];
    }

    /** Mismo nombre normalizado: duplicado seguro, se bloquea. Parecido: se avisa en el alta en línea hasta confirmar. */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $excepto = $this->route('emisor')?->id;

                if ($igual = Emisor::mismoNombre($this->input('nombre'), $excepto)) {
                    $validator->errors()->add('nombre', "Ya existe «{$igual->nombre}».");
                } elseif ($this->routeIs('emisores.rapido') && ! $this->boolean('confirmado')
                    && ($parecidos = Emisor::parecidos($this->input('nombre'), $excepto))->isNotEmpty()) {
                    $validator->errors()->add('nombre', 'Parecido a «'.$parecidos->pluck('nombre')->join('», «').'». Si es otro, vuelve a crearlo para confirmar.');
                }
            },
        ];
    }

    /** @return array{nombre: string, tipo: string, activo?: bool} */
    public function datos(): array
    {
        return $this->safe()->only(['nombre', 'tipo', 'activo']);
    }
}
