<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'activo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    // Igual que el default de la columna, para que un modelo recién creado no se lea como inactivo.
    protected $attributes = ['activo' => true];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    /**
     * Usuarios activos para un Select.
     *
     * @return list<array{value: int, label: string}>
     */
    public static function opciones(): array
    {
        return self::where('activo', true)->orderBy('name')->get(['id', 'name', 'email'])
            ->map(fn (User $u) => ['value' => $u->id, 'label' => "{$u->name} ({$u->email})"])
            ->all();
    }

    /**
     * Áreas de las que hoy es titular o suplente, con sus dependientes: el coordinador de escuela ve toda su escuela (5).
     *
     * @return list<int>
     */
    public function areasVigentes(): array
    {
        $propias = AreaResponsable::where('user_id', $this->id)->vigentes()->pluck('area_id')->unique()->values()->all();
        if ($propias === []) {
            return [];
        }

        // UNION (no UNION ALL) corta un eventual ciclo en la jerarquía.
        return array_map('intval', DB::table(DB::raw('(WITH RECURSIVE arbol(id) AS (
                SELECT id FROM areas WHERE id IN ('.implode(',', array_map('intval', $propias)).')
                UNION SELECT a.id FROM areas a JOIN arbol ON a.parent_id = arbol.id WHERE a.deleted_at IS NULL
            ) SELECT id FROM arbol) AS arbol'))->orderBy('id')->pluck('id')->all());
    }
}
