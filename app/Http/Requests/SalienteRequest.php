<?php

namespace App\Http\Requests;

use App\Models\DocumentoSaliente;
use App\Models\Expediente;
use App\Policies\SalientePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SalienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $saliente = $this->route('saliente');

        return $saliente ? $this->user()->can('update', $saliente) : $this->user()->can('create', DocumentoSaliente::class);
    }

    public function rules(): array
    {
        $areas = SalientePolicy::areasEmisoras($this->user());

        return [
            'expediente_id' => ['nullable', 'integer', 'exists:expedientes,id'],
            'plantilla_id' => ['nullable', 'integer', Rule::exists('plantillas_documento', 'id')->where('activa', true)],
            'tipo_documento_id' => ['required', 'integer', Rule::exists('tipos_documento', 'id')->where('activo', true)],
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')->where('activa', true)->whereNull('deleted_at'), ...($areas === null ? [] : [Rule::in($areas)])],
            'asunto' => ['required', 'string', 'max:300'],
            'cuerpo' => ['required', 'string', 'max:20000'],
            'destinatarios' => ['required', 'array', 'min:1', 'max:200'],
            'destinatarios.*.email' => ['required', 'email', 'max:200', 'distinct'],
            'destinatarios.*.nombre' => ['nullable', 'string', 'max:200'],
            'es_respuesta' => ['required', 'boolean'],
            'requiere_respuesta' => ['required', 'boolean'],
            'plazo_respuesta_dias' => ['nullable', 'required_if_accepted:requiere_respuesta', 'integer', 'min:1', 'max:365'],
            'esperar_firma' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tipo_documento_id' => 'tipo de documento', 'area_id' => 'área que emite', 'destinatarios' => 'destinatarios',
            'destinatarios.*.email' => 'correo del destinatario', 'plazo_respuesta_dias' => 'plazo de respuesta',
        ];
    }

    /** Solo se responde a un expediente que se puede ver. */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $expediente = $this->integer('expediente_id') ? Expediente::find($this->integer('expediente_id')) : null;
                if ($expediente && ! $this->user()->can('view', $expediente)) {
                    $validator->errors()->add('expediente_id', 'No tienes acceso a ese expediente.');
                }
                if ($this->boolean('es_respuesta') && ! $expediente) {
                    $validator->errors()->add('es_respuesta', 'Una respuesta necesita el expediente al que responde.');
                }
                // Enviada, la respuesta deja el trámite atendido: no la emite un área que solo lo tiene en copia.
                if ($this->boolean('es_respuesta') && $expediente && $this->user()->can('view', $expediente) && ! $this->user()->can('responder', $expediente)) {
                    $validator->errors()->add('es_respuesta', 'Solo quien atiende este trámite puede responderlo. Desde una copia, emite el documento sin marcarlo como respuesta.');
                }
            },
        ];
    }
}
