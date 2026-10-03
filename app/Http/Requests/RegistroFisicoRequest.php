<?php

namespace App\Http\Requests;

use App\Models\Expediente;
use App\Services\RegistroFisicoService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RegistroFisicoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Expediente::class);
    }

    public function rules(): array
    {
        return [
            'sha256' => ['required', 'string', 'size:64'],
            'asunto' => ['required', 'string', 'max:500'],
            // Emisor y tipo son obligatorios en papel: sin ellos no hay clave anti-duplicados (7.3.5).
            'emisor_id' => ['required', 'integer', Rule::exists('emisores', 'id')->where('activo', true)->whereNull('fusionado_en_id')],
            'tipo_documento_id' => ['required', 'integer', Rule::exists('tipos_documento', 'id')->where('activo', true)],
            'numero_documento' => ['required', 'string', 'max:150'],
            'fecha_documento' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'folios' => ['required', 'integer', 'min:1', 'max:5000'],
            'motivo_folios' => ['nullable', 'string', 'max:300'],
            'requiere_respuesta' => ['required', 'boolean'],
            'ubicacion_fisica_id' => ['nullable', 'integer', Rule::exists('ubicaciones_fisicas', 'id')->where('activa', true)],
            'confirmar_duplicado' => ['sometimes', 'boolean'],
            // Trámite en curso del registro en papel: su número (anterior al primero del sistema) y su fecha real de ingreso.
            'en_curso' => ['sometimes', 'boolean'],
            'numero_papel' => ['exclude_unless:en_curso,true', 'required', 'integer', 'min:1', 'max:'.RegistroFisicoService::ultimoNumeroEnPapel(),
                Rule::unique('expedientes', 'secuencia')->where('anio', now()->year)],
            'fecha_ingreso' => ['exclude_unless:en_curso,true', 'required', 'date_format:Y-m-d', 'after_or_equal:'.now()->startOfYear()->toDateString(), 'before_or_equal:today'],
        ];
    }

    public function attributes(): array
    {
        return [
            'emisor_id' => 'emisor', 'tipo_documento_id' => 'tipo de documento', 'numero_documento' => 'N° de documento',
            'fecha_documento' => 'fecha del documento', 'motivo_folios' => 'motivo', 'requiere_respuesta' => 'requiere respuesta', 'ubicacion_fisica_id' => 'ubicación',
            'numero_papel' => 'N° en el registro en papel', 'fecha_ingreso' => 'fecha de ingreso',
        ];
    }

    /** Los folios son las páginas del escaneo; corregirlos exige motivo (7.3.5, punto 1). */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $paginas = Cache::get("registro-fisico:{$this->input('sha256')}")['paginas'] ?? null;
                if ($paginas && $this->integer('folios') !== $paginas && blank($this->input('motivo_folios'))) {
                    $validator->errors()->add('motivo_folios', "El escaneo tiene {$paginas} páginas: indica por qué los folios son otros.");
                }
            },
        ];
    }
}
