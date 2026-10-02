<?php

namespace App\Http\Requests;

use App\Models\ReglaDerivacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReglaDerivacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $regla = $this->route('regla');

        return $regla ? $this->user()->can('update', $regla) : $this->user()->can('create', ReglaDerivacion::class);
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'],
            'tipo_tramite_id' => ['nullable', 'integer', Rule::exists('tipos_tramite', 'id')],
            'palabras_clave' => ['present', 'array', 'max:30'],
            'palabras_clave.*' => ['string', 'distinct', 'max:50'],
            'remitentes' => ['present', 'array', 'max:30'],
            // Correo exacto o dominio (incluye sus subdominios).
            'remitentes.*' => ['string', 'distinct', 'max:150', 'regex:/^([^@\s]+@)?[a-z0-9-]+(\.[a-z0-9-]+)+$/i'],
            'area_destino_id' => ['required', 'integer', Rule::exists('areas', 'id')->where('activa', true)->whereNull('deleted_at')],
            'responsable_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('activo', true)],
            'prioridad' => ['required', 'integer', 'min:1', 'max:9999'],
            'activa' => ['required', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'tipo_tramite_id' => 'tipo de trámite',
            'palabras_clave' => 'palabras clave',
            'palabras_clave.*' => 'palabra clave',
            'remitentes.*' => 'remitente',
            'area_destino_id' => 'área de destino',
            'responsable_id' => 'responsable',
        ];
    }

    public function messages(): array
    {
        return ['remitentes.*.regex' => 'Cada remitente debe ser un correo (ana@ejemplo.edu.pe) o un dominio (ejemplo.edu.pe).'];
    }

    /** Una regla sin condiciones aplicaría a todo expediente. */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->input('tipo_tramite_id') && ! $this->input('palabras_clave') && ! $this->input('remitentes')) {
                    $validator->errors()->add('tipo_tramite_id', 'Indica al menos una condición: tipo de trámite, palabras clave o remitentes.');
                }
            },
        ];
    }

    /** @return array<string, mixed> atributos del modelo */
    public function datos(): array
    {
        $v = $this->validated();

        return [
            'nombre' => $v['nombre'],
            'tipo_tramite_id' => $v['tipo_tramite_id'] ?? null,
            'condicion' => [
                'palabras_clave' => $v['palabras_clave'],
                'remitentes' => array_map('mb_strtolower', $v['remitentes']),
            ],
            'area_destino_id' => $v['area_destino_id'],
            'responsable_id' => $v['responsable_id'] ?? null,
            'prioridad' => $v['prioridad'],
            'activa' => $v['activa'],
        ];
    }
}
