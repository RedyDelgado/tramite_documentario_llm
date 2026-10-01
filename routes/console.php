<?php

use Illuminate\Support\Facades\Schedule;

// Una alteración de la auditoría debe detectarse en menos de un día (sección 9).
Schedule::command('auditoria:verificar')->dailyAt('03:00')->withoutOverlapping();
