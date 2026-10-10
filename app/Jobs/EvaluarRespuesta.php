<?php

namespace App\Jobs;

use App\Models\Correo;
use App\Models\Movimiento;
use App\Services\AuditoriaService;
use App\Services\BorradorIaService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** La IA lee la respuesta de un área y propone si atiende lo pedido; fuera del ingreso, y si la IA está caída solo se reintenta (principio 2). */
class EvaluarRespuesta implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    // Lo que permite la llamada a la IA (180 s), más margen.
    public int $timeout = 240;

    public function backoff(): array
    {
        return [60, 300, 900, 3600, 7200];
    }

    public function __construct(public int $correoId) {}

    public function uniqueId(): string
    {
        return (string) $this->correoId;
    }

    public function handle(BorradorIaService $ia, AuditoriaService $auditoria): void
    {
        $correo = Correo::with(['expediente', 'documentos'])->find($this->correoId);
        if (! $correo?->expediente) {
            return;
        }
        $derivacion = Movimiento::where('expediente_id', $correo->expediente_id)->where('tipo', 'derivacion')->latest('id')->first();
        $pedido = implode("\n", array_filter([
            'Asunto del trámite: '.$correo->expediente->asunto,
            $derivacion?->instruccion ? "Instrucción: {$derivacion->instruccion}" : null,
            $derivacion?->nota ? "Indicaciones: {$derivacion->nota}" : null,
        ]));
        $respuesta = implode("\n", array_filter([
            "Asunto: {$correo->asunto}",
            $correo->cuerpo_texto,
            ...$correo->documentos->map(fn ($d) => $d->texto_extraido ? "Adjunto «{$d->nombre_original}»: {$d->texto_extraido}" : "Adjunto «{$d->nombre_original}»"),
        ]));

        $resultado = $ia->evaluarRespuesta($pedido, $respuesta);
        if ($resultado === null) {
            return;
        }
        $correo->forceFill(['ia_respuesta' => $resultado['responde'] ? 'responde' : 'no_relacionado', 'ia_motivo' => $resultado['motivo']])->save();
        $auditoria->registrar('ia.respuesta_evaluada', $correo->expediente, despues: [
            'correo_id' => $correo->id, 'responde' => $resultado['responde'], 'modelo' => config('tramite.llm.modelo'),
        ]);
    }
}
