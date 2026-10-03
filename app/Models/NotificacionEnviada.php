<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** Un aviso del sistema a una persona y su estado de entrega: pendiente, enviado, fallido o rebotado. */
#[Fillable(['user_id', 'email', 'tipo', 'expedientes', 'message_id'])]
class NotificacionEnviada extends Model
{
    protected $table = 'notificaciones_enviadas';

    public const ESTADOS = ['pendiente' => 'Pendiente', 'enviado' => 'Enviado', 'fallido' => 'Fallido', 'rebotado' => 'Rebotado'];

    public const TIPOS = ['resumen_diario' => 'Resumen diario'];

    protected function casts(): array
    {
        return ['expedientes' => 'array', 'enviado_at' => 'datetime', 'rebotado_at' => 'datetime'];
    }

    /** Message-ID propio (sin <>): con él se reconoce el rebote que lo cita. */
    public static function nuevoMessageId(string $tipo): string
    {
        $dominio = Str::after((string) config('mail.from.address'), '@') ?: 'tramite.local';

        return Str::slug($tipo).'-'.Str::uuid().'@'.$dominio;
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
