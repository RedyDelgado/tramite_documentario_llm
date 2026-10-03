<?php

namespace App\Http\Requests;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $usuario = $this->route('usuario');

        return $usuario
            ? $this->user()->can('update', $usuario)
            : $this->user()->can('create', User::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->input('email')))]);
    }

    public function rules(): array
    {
        $dominio = config('tramite.google_dominio');

        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => [
                'required', 'email', 'max:150',
                Rule::unique('users', 'email')->ignore($this->route('usuario')),
                // Solo puede ingresar con Google una cuenta del dominio; otra quedaría sin acceso.
                ...($dominio ? ['ends_with:@'.mb_strtolower($dominio)] : []),
            ],
            'rol' => ['required', Rule::in(array_keys(RolesSeeder::ROLES))],
            'activo' => ['required', 'boolean'],
            // Delegar la configuración (5.1, pendiente 8) sin dar el resto del superadmin.
            'administra_configuracion' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'nombre', 'email' => 'correo'];
    }
}
