<?php

namespace App\Console\Commands;

use App\Correo\MailboxDriver;
use App\Services\BuzonService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('correo:estadisticas {--desde= : Fecha inicial (por defecto CORREO_BACKFILL_DESDE)} {--top=15 : Remitentes a mostrar}')]
#[Description('Cuenta correos por día y por remitente sin guardar nada, para dimensionar el servidor (7.3.3)')]
class EstadisticasCorreo extends Command
{
    public function handle(MailboxDriver $buzon): int
    {
        $desde = CarbonImmutable::parse($this->option('desde') ?: app(BuzonService::class)->desde()->toDateString())->startOfDay();
        $porDia = [];
        $porRemitente = [];

        foreach ($buzon->resumen($desde) as $mensaje) {
            $dia = $mensaje['fecha']->setTimezone(config('app.timezone'))->format('Y-m-d');
            $porDia[$dia] = ($porDia[$dia] ?? 0) + 1;
            $porRemitente[$mensaje['remitente']] = ($porRemitente[$mensaje['remitente']] ?? 0) + 1;
        }

        $total = array_sum($porDia);
        if ($total === 0) {
            $this->warn("No hay correos desde {$desde->toDateString()}.");

            return self::SUCCESS;
        }

        ksort($porDia);
        arsort($porRemitente);
        $this->info("{$total} correos desde {$desde->toDateString()} en ".count($porDia).' días con correo; promedio '.round($total / count($porDia), 1).' por día; máximo '.max($porDia).'.');
        $this->table(['Día', 'Correos'], collect($porDia)->map(fn ($n, $dia) => [$dia, $n])->values());
        $this->table(['Remitente', 'Correos'], collect($porRemitente)->take((int) $this->option('top'))->map(fn ($n, $r) => [$r, $n])->values());

        return self::SUCCESS;
    }
}
