<?php

namespace App\Services;

use App\Correo\SalidaCorreo;
use App\Exceptions\ReglaDeNegocio;
use App\Jobs\EnviarDocumento;
use App\Models\Documento;
use App\Models\DocumentoSaliente;
use App\Models\Envio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/** Envío automático de lo aprobado (7.3.4): un correo por destinatario, con el código en el asunto y copia oculta al buzón central. */
class EnvioService
{
    public function __construct(
        private readonly SalidaCorreo $salida,
        private readonly AuditoriaService $auditoria,
        private readonly AtencionService $atencion,
        private readonly PlazoService $plazos,
    ) {}

    /** Crea un envío por destinatario y los encola; aprobado el documento, sale sin intervención manual. */
    public function despachar(DocumentoSaliente $saliente): void
    {
        if ($saliente->estado !== 'aprobado') {
            throw new ReglaDeNegocio('Solo se envía un documento aprobado.');
        }
        if ($saliente->esperar_firma && ! $saliente->ruta_firmado) {
            throw new ReglaDeNegocio('Falta subir el documento final con su número antes de enviarlo.');
        }

        DB::transaction(function () use ($saliente) {
            foreach ($saliente->destinatarios as $d) {
                $envio = Envio::firstOrCreate(['documento_saliente_id' => $saliente->id, 'email' => $d['email']], ['nombre' => $d['nombre'] ?? null]);
                if ($envio->estado === 'pendiente') {
                    EnviarDocumento::dispatch($envio->id)->afterCommit();
                }
            }
        });
    }

    /** Sube el documento final (Word o PDF firmado, con su número) y lo envía (7.3.4). */
    public function subirFirmado(DocumentoSaliente $saliente, string $contenido, string $nombre): void
    {
        if ($saliente->estado !== 'aprobado') {
            throw new ReglaDeNegocio('El documento final se sube a un documento aprobado y aún no enviado.');
        }
        ['ruta' => $ruta, 'sha256' => $sha] = Documento::guardarArchivo($contenido);
        $saliente->forceFill(['ruta_firmado' => $ruta, 'sha256_firmado' => $sha, 'nombre_firmado' => $nombre])->save();
        $this->auditoria->registrar('saliente.firmado_adjunto', $saliente, despues: ['sha256' => $sha, 'nombre' => $nombre]);

        if ($saliente->esperar_firma) {
            $this->despachar($saliente);
        }
    }

    /** Envía un correo; idempotente: un envío que ya salió no vuelve a salir. */
    public function enviar(Envio $envio): void
    {
        $envio = Envio::with('saliente.expediente')->findOrFail($envio->id);
        if ($envio->estado !== 'pendiente') {
            return;
        }
        $s = $envio->saliente;
        // Sale desde la cuenta principal del buzón central, si hay una conectada.
        $central = app(BuzonService::class)->principal()?->cuenta ?? (config('tramite.salientes.buzon_central') ?: config('mail.from.address'));
        $codigo = $s->expediente?->codigo;
        $extension = strtolower(pathinfo((string) $s->nombre_firmado, PATHINFO_EXTENSION)) ?: 'pdf';

        $email = (new Email)
            ->from(new Address($central, config('app.name')))
            ->to(new Address($envio->email, (string) $envio->nombre))
            // El código en el asunto enlaza la respuesta con su expediente aunque el cliente pierda el hilo (7.1, 7.2).
            ->subject(($codigo ? "[{$codigo}] " : '')."{$s->numero} - {$s->asunto}")
            ->text(($s->cuerpo ? trim($s->cuerpo)."\n\n" : "Se adjunta {$s->numero}.\n\n")."Al responder, conserve el asunto de este correo.\n\n{$s->area->nombre}")
            ->attach(Storage::disk('originales')->get($s->ruta_firmado), Str::slug($s->numero).".{$extension}", DocumentoSaliente::mime($s->nombre_firmado));
        if ($central) {
            $email->bcc($central);
        }
        $email->getHeaders()->addIdHeader('Message-ID', 'envio-'.$envio->id.'-'.Str::lower(Str::random(12)).'@'.(Str::after((string) $central, '@') ?: 'tramite.local'));

        $resultado = $this->salida->enviar($email);

        DB::transaction(function () use ($envio, $resultado, $s) {
            $envio->forceFill(['estado' => 'enviado', 'message_id' => $resultado['message_id'], 'proveedor_id' => $resultado['proveedor_id'], 'enviado_at' => now()])->save();
            $this->auditoria->registrar('envio.enviado', $envio, despues: ['saliente_id' => $s->id, 'email' => $envio->email, 'message_id' => $resultado['message_id']]);
            $this->completar($s);
        });
    }

    /** Agotados los reintentos, el envío queda fallido y visible. */
    public function fallo(Envio $envio, Throwable $e): void
    {
        $envio->forceFill(['estado' => 'fallido', 'detalle' => Str::limit($e->getMessage(), 500)])->save();
        $this->auditoria->registrar('envio.fallido', $envio, despues: ['email' => $envio->email]);
    }

    /** Cuando sale el último correo: el documento queda enviado, corre su plazo de respuesta y, si respondía, el expediente queda atendido. */
    private function completar(DocumentoSaliente $s): void
    {
        $s = DocumentoSaliente::lockForUpdate()->findOrFail($s->id);
        if ($s->estado === 'enviado' || $s->envios()->where('estado', 'pendiente')->exists()) {
            return;
        }

        $limite = $s->requiere_respuesta && $s->plazo_respuesta_dias
            ? $this->plazos->sumarDiasHabiles(now(), $s->plazo_respuesta_dias, $s->area_id)->toDateString()
            : null;
        $s->forceFill(['estado' => 'enviado', 'enviado_at' => now(), 'fecha_limite_respuesta' => $limite])->save();
        $this->auditoria->registrar('saliente.enviado', $s, antes: ['estado' => 'aprobado'], despues: ['estado' => 'enviado', 'fecha_limite_respuesta' => $limite]);

        if ($s->es_respuesta && $s->expediente) {
            // «Atendido» se marca solo al enviar la respuesta vinculada (7.3.5, punto 7).
            $this->atencion->atenderConRespuesta($s->expediente, $s);
        }
    }
}
