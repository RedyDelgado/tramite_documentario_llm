<?php

namespace App\Services;

use App\Models\Auditoria;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Única puerta de escritura de la auditoría (sección 9). */
class AuditoriaService
{
    // Clave del advisory lock que serializa la cadena de hashes.
    private const BLOQUEO = 7_352_001;

    /**
     * Registra un evento encadenado al anterior.
     *
     * @param  array<string, mixed>|null  $antes
     * @param  array<string, mixed>|null  $despues
     */
    public function registrar(
        string $accion,
        Model|string $entidad,
        int|string|null $entidadId = null,
        ?array $antes = null,
        ?array $despues = null,
        ?string $actor = null,
    ): void {
        $usuarioId = Auth::id();
        // Una petición con ruta es HTTP; en jobs y comandos no hay ruta ni IP que registrar.
        $enHttp = request()->route() !== null;

        $campos = [
            'fecha_hora' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'usuario_id' => $usuarioId,
            'actor' => $actor ?? ($usuarioId ? 'usuario' : 'sistema'),
            'accion' => $accion,
            'entidad' => $entidad instanceof Model ? $entidad->getMorphClass() : $entidad,
            'entidad_id' => $entidad instanceof Model ? (string) $entidad->getKey() : ($entidadId === null ? null : (string) $entidadId),
            // Ida y vuelta por JSON: se hashea exactamente lo que jsonb devolverá al verificar.
            'valor_anterior' => $antes === null ? null : json_decode(json_encode($antes), true),
            'valor_nuevo' => $despues === null ? null : json_decode(json_encode($despues), true),
            'ip' => $enHttp ? request()->ip() : null,
            'user_agent' => $enHttp ? request()->userAgent() : null,
        ];

        // ponytail: bloqueo global, suficiente para el volumen esperado; particionar la cadena si escala.
        DB::transaction(function () use ($campos) {
            DB::select('SELECT pg_advisory_xact_lock(?)', [self::BLOQUEO]);
            $anterior = DB::table('auditoria')->orderByDesc('id')->value('hash_registro');

            DB::table('auditoria')->insert([
                ...$campos,
                'valor_anterior' => $campos['valor_anterior'] === null ? null : json_encode($campos['valor_anterior'], JSON_UNESCAPED_UNICODE),
                'valor_nuevo' => $campos['valor_nuevo'] === null ? null : json_encode($campos['valor_nuevo'], JSON_UNESCAPED_UNICODE),
                'hash_anterior' => $anterior,
                'hash_registro' => Auditoria::calcularHash($campos, $anterior),
            ]);
        });
    }

    /** Registra los atributos que cambió el último save() del modelo, con su valor previo. */
    public function registrarCambios(string $accion, Model $modelo): void
    {
        $despues = collect($modelo->getChanges())->except(['updated_at'])->all();

        if ($despues !== []) {
            $this->registrar($accion, $modelo, antes: array_intersect_key($modelo->getPrevious(), $despues), despues: $despues);
        }
    }
}
