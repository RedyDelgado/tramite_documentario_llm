<?php

namespace App\Console\Commands;

use App\Models\Auditoria;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('auditoria:verificar')]
#[Description('Recorre la cadena de hashes de la auditoría y reporta cualquier fila alterada')]
class VerificarAuditoria extends Command
{
    public function handle(): int
    {
        $anterior = null;
        $alteradas = [];
        $total = 0;

        DB::table('auditoria')->orderBy('id')->lazyById(1000)->each(function (object $fila) use (&$anterior, &$alteradas, &$total) {
            $total++;
            $campos = [
                'fecha_hora' => CarbonImmutable::parse($fila->fecha_hora)->utc()->format('Y-m-d\TH:i:s.u\Z'),
                'usuario_id' => $fila->usuario_id === null ? null : (int) $fila->usuario_id,
                'actor' => $fila->actor,
                'accion' => $fila->accion,
                'entidad' => $fila->entidad,
                'entidad_id' => $fila->entidad_id,
                'valor_anterior' => $fila->valor_anterior === null ? null : json_decode($fila->valor_anterior, true),
                'valor_nuevo' => $fila->valor_nuevo === null ? null : json_decode($fila->valor_nuevo, true),
                'ip' => $fila->ip,
                'user_agent' => $fila->user_agent,
            ];

            if ($fila->hash_anterior !== $anterior || $fila->hash_registro !== Auditoria::calcularHash($campos, $fila->hash_anterior)) {
                $alteradas[] = $fila->id;
            }

            $anterior = $fila->hash_registro;
        });

        if ($alteradas !== []) {
            $this->error('Cadena rota en '.count($alteradas).' de '.$total.' registros. IDs: '.implode(', ', array_slice($alteradas, 0, 50)));

            return self::FAILURE;
        }

        $this->info("Cadena íntegra: {$total} registros verificados.");

        return self::SUCCESS;
    }
}
