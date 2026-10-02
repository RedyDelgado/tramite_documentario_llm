<?php

use App\Jobs\IngestarCorreos;
use App\Models\Feriado;
use Illuminate\Support\Facades\Schedule;

// Antes de la verificación de la auditoría, para que el respaldo no compita con ella.
$respaldo = Schedule::command('respaldo:crear')->dailyAt('02:30')->withoutOverlapping();
// Un respaldo que falla en silencio se descubre el día que hace falta restaurar.
if ($superadmin = config('tramite.superadmin.email')) {
    $respaldo->emailOutputOnFailure($superadmin);
}

// Una alteración de la auditoría debe detectarse en menos de un día (sección 9).
Schedule::command('auditoria:verificar')->dailyAt('03:00')->withoutOverlapping();

// Solo con el buzón configurado y autorizado (CORREO_ACTIVO).
Schedule::job(new IngestarCorreos)->everyMinute()->when(fn () => config('tramite.correo.activo'));

// Los vencimientos y los días sin movimiento cambian el color sin que nadie toque el expediente (8).
Schedule::command('semaforos:recalcular')->hourly()->withoutOverlapping();

// Resumen a cada coordinador en días laborables, antes de empezar la jornada (8).
Schedule::command('resumen:diario')->weekdays()->at('07:30')->withoutOverlapping()
    ->skip(fn () => Feriado::whereNull('area_id')->whereDate('fecha', today())->exists());
