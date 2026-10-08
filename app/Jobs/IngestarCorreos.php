<?php

namespace App\Jobs;

use App\Services\BuzonService;
use App\Services\IngestaCorreoService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Lee un lote de cada cuenta del buzón central; si quedó más, se vuelve a encolar solo hasta terminar: un clic en
 * «Descargar correos» baja todo. También lo programa la descarga automática cada minuto. Idempotente por Message-ID.
 */
class IngestarCorreos implements ShouldQueue
{
    use Queueable;

    private const SEGUNDOS = 40;

    public function handle(BuzonService $buzones, IngestaCorreoService $ingesta): void
    {
        $lote = (int) config('tramite.correo.lote');

        // Trabaja hasta 40 s y deja que la siguiente pasada continúe: el worker corta los jobs al minuto.
        $hasta = microtime(true) + self::SEGUNDOS;

        // Una sola lectura a la vez (la programada y la del botón pueden coincidir); el candado vence si el job muere.
        $hayMas = Cache::lock('ingesta-correo', 90)->get(function () use ($buzones, $ingesta, $lote, $hasta) {
            $hayMas = false;
            foreach ($buzones->lectores() as [$nombre, $lector, $cuenta]) {
                if (microtime(true) > $hasta) {
                    return true;
                }
                try {
                    $r = $ingesta->procesarPendientes($lector, $lote, $nombre, $cuenta, $hasta);
                    $hayMas = $hayMas || ($r['cortado'] ?? false) || ($r['procesados'] > 0 && $lote <= $r['procesados'] + $r['fallidos']);
                } catch (Throwable $e) {
                    // Una cuenta caída (acceso revocado) no frena a las demás; su error queda en Buzón central.
                    report($e);
                }
            }

            return $hayMas;
        });

        if ($hayMas) {
            Cache::put(BuzonService::EN_CURSO, true, 120);
            self::dispatch()->delay(now()->addSeconds(2));
        } elseif ($hayMas === false) {
            Cache::forget(BuzonService::EN_CURSO);
        }
    }
}
