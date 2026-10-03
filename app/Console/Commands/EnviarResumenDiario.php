<?php

namespace App\Console\Commands;

use App\Enums\EstadoExpediente;
use App\Mail\ResumenDiario;
use App\Models\Expediente;
use App\Models\NotificacionEnviada;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('resumen:diario')]
#[Description('Envía a cada coordinador sus expedientes abiertos con su semáforo y enlace directo')]
class EnviarResumenDiario extends Command
{
    // Lo urgente primero.
    private const ORDEN = ['rojo' => 0, 'amarillo' => 1, 'verde' => 2, 'gris' => 3];

    public function handle(AuditoriaService $auditoria): int
    {
        $enviados = 0;

        User::role('coordinador')->where('activo', true)->each(function (User $coordinador) use ($auditoria, &$enviados) {
            // Solo lo que atiende: lo que tiene en copia no es pendiente suyo.
            $expedientes = Expediente::visiblesPara($coordinador, copias: false)
                ->whereIn('estado', [EstadoExpediente::Derivado, EstadoExpediente::EnAtencion])
                ->orderBy('fecha_limite')
                ->get()
                ->sortBy(fn (Expediente $e) => self::ORDEN[$e->semaforo?->value] ?? 9)
                ->values();

            // Sin pendientes no se envía nada: un correo vacío enseña a ignorar el resumen.
            if ($expedientes->isEmpty()) {
                return;
            }

            $notificacion = NotificacionEnviada::create([
                'user_id' => $coordinador->id,
                'email' => $coordinador->email,
                'tipo' => 'resumen_diario',
                'expedientes' => $expedientes->pluck('id')->all(),
                'message_id' => NotificacionEnviada::nuevoMessageId('resumen'),
            ]);
            Mail::to($coordinador)->queue(new ResumenDiario($coordinador, $expedientes, $notificacion));
            $auditoria->registrar('notificacion.resumen_diario', $coordinador, despues: [
                'destinatario' => $coordinador->email,
                'expedientes' => $expedientes->pluck('id')->all(),
                'notificacion_id' => $notificacion->id,
            ]);
            $enviados++;
        });

        $this->info("Resúmenes encolados: {$enviados}.");

        return self::SUCCESS;
    }
}
