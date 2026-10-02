<?php

namespace App\Services;

use App\Correo\MensajeLeido;
use App\Models\Correo;
use App\Models\Envio;
use App\Models\Expediente;
use Illuminate\Support\Str;

/** Lo que vuelve al buzón central de lo enviado (7.2, 7.3.4): rebotes y respuestas, ligados a su envío por Message-ID. */
class EntregaService
{
    public function __construct(private readonly AuditoriaService $auditoria) {}

    /** Aviso de no entrega (DSN): de mailer-daemon/postmaster o con Content-Type multipart/report. */
    public function esRebote(MensajeLeido $m): bool
    {
        return preg_match('/^(mailer-daemon|postmaster)@/i', $m->deEmail) === 1
            || str_contains(mb_strtolower($m->encabezados['content-type'] ?? ''), 'multipart/report');
    }

    /** El envío al que responde o del que avisa el mensaje: por In-Reply-To/References y, en un rebote, por el Message-ID citado. */
    public function envioReferido(MensajeLeido $m): ?Envio
    {
        $ids = $m->enRespuestaA;
        if ($this->esRebote($m)) {
            $texto = $m->cuerpo."\n".implode("\n", array_map(fn ($a) => str_starts_with($a->mime, 'message/') || str_starts_with($a->mime, 'text/') ? $a->contenido : '', $m->adjuntos));
            preg_match_all('/^Message-I[Dd]:\s*<([^>]+)>/mi', $texto, $citados);
            $ids = [...$ids, ...$citados[1]];
        }

        return $ids === [] ? null : Envio::whereIn('message_id', $ids)->with('saliente')->orderBy('id')->first();
    }

    /** Rebote: el envío queda `rebotado`, visible en el documento, con el diagnóstico del servidor. */
    public function registrarRebote(MensajeLeido $m, Correo $correo): ?Envio
    {
        $envio = $this->envioReferido($m);
        if (! $envio || $envio->estado === 'rebotado') {
            return $envio;
        }
        preg_match('/^Diagnostic-Code:\s*(.+)$/mi', $m->cuerpo."\n".implode("\n", array_map(fn ($a) => str_starts_with($a->mime, 'message/') ? $a->contenido : '', $m->adjuntos)), $diagnostico);
        $detalle = Str::limit(trim($diagnostico[1] ?? strtok($m->cuerpo, "\n") ?: $m->asunto), 500);

        $envio->forceFill(['estado' => 'rebotado', 'rebotado_at' => now(), 'detalle' => $detalle])->save();
        $this->auditoria->registrar('envio.rebotado', $envio, despues: ['email' => $envio->email, 'correo_id' => $correo->id, 'detalle' => $detalle]);

        return $envio;
    }

    /**
     * Respuesta a un documento que la exigía: deja de correr su plazo (7.3.4). Se reconoce por el hilo o, si el cliente
     * lo perdió, por el código del asunto y el remitente que recibió el envío.
     */
    public function registrarRespuesta(MensajeLeido $m, Expediente $expediente): void
    {
        $envio = $this->envioReferido($m) ?? Envio::where('email', $m->deEmail)
            ->whereHas('saliente', fn ($q) => $q->where('expediente_id', $expediente->id))
            ->with('saliente')->latest('id')->first();
        $saliente = $envio?->saliente;
        if (! $saliente || ! $saliente->requiere_respuesta || $saliente->respondido_at || $saliente->estado !== 'enviado') {
            return;
        }

        $saliente->forceFill(['respondido_at' => now()])->save();
        $this->auditoria->registrar('saliente.respondido', $saliente, despues: ['email' => $m->deEmail, 'expediente_id' => $expediente->id]);
    }
}
