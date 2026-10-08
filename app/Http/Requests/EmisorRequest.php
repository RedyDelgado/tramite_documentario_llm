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
            // En el alta en línea se deduce del nombre (Emisor::adivinarClase); en el catálogo se elige.
            'clase' => ['sometimes', Rule::in(array_keys(Emisor::CLASES))],
            'institucion_id' => ['nullable', 'integer', Rule::exists('emisores', 'id')->where('activo', true)->where('clase', 'institucion')->whereNull('fusionado_en_id'), Rule::notIn(array_filter([$this->route('emisor')?->id]))],
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

    /** @return array{nombre: string, tipo: string, clase: string, institucion_id: ?int, activo?: bool} */
    public function datos(): array
    {
        $datos = $this->safe()->only(['nombre', 'tipo', 'clase', 'institucion_id', 'activo']);
        $datos['clase'] ??= $this->route('emisor')?->clase ?? Emisor::adivinarClase($datos['nombre']);
        // Una institución no pertenece a otra en este catálogo.
        if ($datos['clase'] === 'institucion') {
            $datos['institucion_id'] = null;
        }

        return $datos;
    }
}
