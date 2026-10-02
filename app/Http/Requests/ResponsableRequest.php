<?php

namespace App\Http\Requests;

use App\Models\AreaResponsable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ResponsableRequest extends FormRequest
{
    public function authorize(): bool
    {
        $responsable = $this->route('responsable');

        return $responsable ? $this->user()->can('update', $responsable) : $this->user()->can('create', AreaResponsable::class);
    }

    public function rules(): array
    {
        return [
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')->where('activa', true)->whereNull('deleted_at')],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('activo', true)],
            'tipo' => ['required', Rule::in(array_keys(AreaResponsable::TIPOS))],
            'vigente_desde' => ['required', 'date_format:Y-m-d'],
            'vigente_hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:vigente_desde'],
        ];
    }

    public function attributes(): array
    {
        return ['area_id' => 'área', 'user_id' => 'usuario', 'vigente_desde' => 'vigente desde', 'vigente_hasta' => 'vigente hasta'];
    }

    /** Un área tiene un solo titular a la vez; los suplentes pueden coincidir (cobertura por vacaciones). */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty() || $this->input('tipo') !== 'titular') {
                    return;
                }

                $hasta = $this->input('vigente_hasta');
                $cruce = AreaResponsable::where('area_id', $this->integer('area_id'))
                    ->where('tipo', 'titular')
                    ->when($this->route('responsable'), fn ($q, $r) => $q->whereKeyNot($r->id))
                    ->when($hasta, fn ($q) => $q->whereDate('vigente_desde', '<=', $hasta))
                    ->where(fn ($q) => $q->whereNull('vigente_hasta')->orWhereDate('vigente_hasta', '>=', $this->input('vigente_desde')))
                    ->with('user:id,name')
                    ->first();

                if ($cruce) {
                    $validator->errors()->add('vigente_desde', "En esas fechas el titular es {$cruce->user->name}; cierra su vigencia primero.");
                }
            },
        ];
    }
}
