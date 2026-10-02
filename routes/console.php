<?php

use App\Jobs\IngestarCorreos;
use Illuminate\Support\Facades\Schedule;

// Una alteración de la auditoría debe detectarse en menos de un día (sección 9).
Schedule::command('auditoria:verificar')->dailyAt('03:00')->withoutOverlapping();

// Solo con el buzón configurado y autorizado (CORREO_ACTIVO).
Schedule::job(new IngestarCorreos)->everyMinute()->when(fn () => config('tramite.correo.activo'));

// Los vencimientos y los días sin movimiento cambian el color sin que nadie toque el expediente (8).
Schedule::command('semaforos:recalcular')->hourly()->withoutOverlapping();
