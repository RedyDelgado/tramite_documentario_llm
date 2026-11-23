<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocio;
use App\Models\Conversacion;
use App\Models\Mensaje;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Única vía de escritura del chat entre usuarios; la auditoría guarda quién y cuándo, nunca el texto (Ley 29733). */
class MensajeriaService
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    /**
     * Con una persona devuelve la conversación que ya tenían; con varias crea un grupo nuevo.
     *
     * @param  list<int>  $usuarioIds  sin contar a quien la abre
     *
     * @throws ReglaDeNegocio
     */
    public function abrir(User $yo, array $usuarioIds, ?string $nombre = null): Conversacion
    {
        $ids = collect($usuarioIds)->map(fn ($id) => (int) $id)->reject(fn (int $id) => $id === $yo->id)->unique()->values();
        if ($ids->isEmpty() || User::whereIn('id', $ids)->where('activo', true)->count() !== $ids->count()) {
            throw new ReglaDeNegocio('Elige al menos una persona activa del sistema.');
        }

        return DB::transaction(function () use ($yo, $ids, $nombre) {
            $clave = $ids->count() === 1 ? Conversacion::claveEntre($yo->id, $ids[0]) : null;
            if ($clave && ($existente = Conversacion::where('clave', $clave)->first())) {
                return $existente;
            }

            $conversacion = Conversacion::create([
                'nombre' => $clave ? null : (trim((string) $nombre) ?: null),
                'clave' => $clave,
                'creada_por' => $yo->id,
            ]);
            $conversacion->participantes()->attach([$yo->id, ...$ids]);
            $this->auditoria->registrar('conversacion.creada', $conversacion, despues: [
                'nombre' => $conversacion->nombre,
                'participantes' => [$yo->id, ...$ids],
            ]);

            return $conversacion;
        });
    }

    public function enviar(User $autor, Conversacion $conversacion, string $texto): Mensaje
    {
        return DB::transaction(function () use ($autor, $conversacion, $texto) {
            $mensaje = $conversacion->mensajes()->create(['user_id' => $autor->id, 'texto' => trim($texto)]);
            $conversacion->update(['ultimo_mensaje_at' => $mensaje->created_at]);
            // Lo propio no cuenta como pendiente para quien lo escribe.
            $conversacion->participantes()->updateExistingPivot($autor->id, ['leido_hasta' => $mensaje->id]);
            $this->auditoria->registrar('mensaje.enviado', $mensaje, despues: ['conversacion_id' => $conversacion->id]);

            return $mensaje;
        });
    }

    /** Marcar como leído no se audita: no cambia nada de lo que el sistema custodia. */
    public function marcarLeido(User $user, Conversacion $conversacion): void
    {
        $ultimo = $conversacion->mensajes()->max('id');
        if ($ultimo !== null) {
            $conversacion->participantes()->updateExistingPivot($user->id, ['leido_hasta' => $ultimo]);
        }
    }

    /** Mensajes de otros que aún no vio, en todas sus conversaciones. */
    public function sinLeer(User $user): int
    {
        return Mensaje::query()
            ->join('conversacion_usuario as cu', fn ($j) => $j->on('cu.conversacion_id', '=', 'mensajes.conversacion_id')->where('cu.user_id', $user->id))
            ->where('mensajes.user_id', '!=', $user->id)
            ->where(fn ($q) => $q->whereNull('cu.leido_hasta')->orWhereColumn('mensajes.id', '>', 'cu.leido_hasta'))
            ->count();
    }
}
