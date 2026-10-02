<?php

namespace App\Http\Resources;

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UsuarioResource extends JsonResource
{
    /** Forma espejada en resources/js/types/index.ts (UsuarioFila). */
    public function toArray(Request $request): array
    {
        $rol = $this->roles->first()?->name;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'rol' => $rol,
            'rol_etiqueta' => RolesSeeder::ROLES[$rol] ?? null,
            'activo' => $this->activo,
            'actualizado' => $this->updated_at?->toIso8601String(),
        ];
    }
}
