<?php

namespace App\Services;

use App\Correo\Adjunto;
use App\Correo\LectorEml;
use App\Correo\MailboxDriver;
use App\Correo\MensajeLeido;
use App\Enums\EstadoExpediente;
use App\Enums\OrigenExpediente;
use App\Models\Correo;
use App\Models\Documento;
use App\Models\Expediente;
use App\Models\ReglaNoTramite;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/** Convierte cada correo del buzón en un expediente (o lo anexa a uno existente) — sección 7.1. */
class IngestaCorreoService
{
    private const ASUNTO_REENVIO = '/^\s*(rv|fw|fwd|reenv(iado)?)\s*:/iu';

    private const CUERPO_REENVIO = '/(mensaje reenviado|forwarded message|mensaje original|original message)/iu';

    public function __construct(
        private readonly LectorEml $lector,
        private readonly TextoDocumentoService $texto,
        private readonly EntregaService $entregas,
        private readonly AuditoriaService $auditoria,
        private readonly AntivirusService $antivirus,
    ) {}

    /**
     * Procesa los pendientes del buzón; un mensaje que falla queda sin marcar y se reintenta en la próxima pasada.
     *
     * @return array{procesados: int, fallidos: int}
     */
    public function procesarPendientes(MailboxDriver $buzon, int $limite): array
    {
        $desde = CarbonImmutable::parse(config('tramite.correo.backfill_desde'))->startOfDay();
        $resultado = ['procesados' => 0, 'fallidos' => 0];

        try {
            foreach ($buzon->pendientes($desde, $limite) as $mensaje) {
                try {
                    $this->procesar($mensaje->contenido, $mensaje->uid);
                    $buzon->marcarProcesado($mensaje);
                    $resultado['procesados']++;
                } catch (Throwable $e) {
                    report($e);
                    $resultado['fallidos']++;
                }
            }
        } catch (Throwable $e) {
            // No se pudo ni leer el buzón (token revocado, sin red): queda a la vista en Buzón central.
            app(BuzonService::class)->registrarLectura($resultado, $e->getMessage());
            throw $e;
        }
        app(BuzonService::class)->registrarLectura($resultado);

        return $resultado;
    }

    /** Ingresa un .eml; reprocesar el mismo mensaje devuelve el expediente existente sin duplicar. */
    public function procesar(string $eml, ?string $uidExterno = null): Expediente
    {
        $mensaje = $this->lector->leer($eml);
        $hash = hash('sha256', $eml);
        // Sin Message-ID, el propio contenido identifica el mensaje.
        $messageId = $mensaje->messageId ?? "sin-message-id-{$hash}";

        if ($existente = Correo::where('message_id', $messageId)->first()) {
            return $existente->expediente;
        }

        $fecha = $mensaje->fecha ?? CarbonImmutable::now();
        $rutaEml = $this->guardarOriginal("correos/{$fecha->format('Y/m')}/{$hash}.eml", $eml);
        $adjuntos = array_map($this->guardarAdjunto(...), $mensaje->adjuntos);

        try {
            $expediente = DB::transaction(fn () => $this->registrar($mensaje, $messageId, $uidExterno, $fecha, $rutaEml, $hash, $adjuntos));
            // Un correo anexado no toca la fila del expediente: se reindexa a mano para buscar su texto.
            $expediente->searchable();

            return $expediente;
        } catch (UniqueConstraintViolationException) {
            // Otro proceso ingresó el mismo mensaje al mismo tiempo.
            return Correo::where('message_id', $messageId)->firstOrFail()->expediente;
        }
    }

    /** @param list<array{adjunto: Adjunto, ruta: string, sha256: string, texto: ?string}> $adjuntos */
    private function registrar(MensajeLeido $mensaje, string $messageId, ?string $uid, CarbonImmutable $fecha, string $rutaEml, string $hash, array $adjuntos): Expediente
    {
        $expediente = $this->expedienteVinculado($mensaje);
        $nuevo = $expediente === null;
        $regla = null;
        $esReenvio = preg_match(self::ASUNTO_REENVIO, $mensaje->asunto) === 1 || preg_match(self::CUERPO_REENVIO, $mensaje->cuerpo) === 1;

        if ($nuevo) {
            $regla = ReglaNoTramite::primeraQueAplica($mensaje);
            [$email, $nombre, $porConfirmar] = $esReenvio ? $this->remitenteOriginal($mensaje) : [$mensaje->deEmail, $mensaje->deNombre, false];

            $expediente = Expediente::create([
                'origen' => OrigenExpediente::Correo,
                'estado' => $this->estadoInicial($fecha, $regla),
                'asunto' => Str::limit($mensaje->asunto !== '' ? $mensaje->asunto : '(sin asunto)', 500, ''),
                'remitente_nombre' => $nombre,
                'remitente_email' => $email,
                'remitente_por_confirmar' => $porConfirmar,
                'fecha_ingreso' => $fecha,
            ]);
        }

        $correo = Correo::create([
            'expediente_id' => $expediente->id,
            'message_id' => $messageId,
            'en_respuesta_a' => $mensaje->enRespuestaA,
            'uid_externo' => $uid,
            'de_email' => $mensaje->deEmail,
            'de_nombre' => $mensaje->deNombre,
            'para' => $mensaje->para,
            'cc' => $mensaje->cc,
            'asunto' => $mensaje->asunto,
            'fecha' => $fecha,
            'cuerpo_texto' => $mensaje->cuerpo,
            'es_reenvio' => $esReenvio,
            'ruta_eml' => $rutaEml,
            'sha256' => $hash,
        ]);

        foreach ($adjuntos as $a) {
            $documento = Documento::create([
                'expediente_id' => $expediente->id,
                'correo_id' => $correo->id,
                'nombre_original' => Str::limit($a['adjunto']->nombre, 250, ''),
                'ruta' => $a['ruta'],
                'mime' => Str::limit($a['adjunto']->mime, 150, ''),
                'tamano' => strlen($a['adjunto']->contenido),
                'sha256' => $a['sha256'],
                'texto_extraido' => $a['texto'],
                'amenaza' => $a['amenaza'],
            ]);
            if ($a['amenaza'] !== null) {
                $this->auditoria->registrar('documento.en_cuarentena', $documento, despues: ['amenaza' => $a['amenaza'], 'sha256' => $a['sha256']]);
            }
        }

        if ($nuevo) {
            $this->auditoria->registrar('expediente.creado', $expediente, despues: [
                'estado' => $expediente->estado->value, 'origen' => 'correo', 'regla_no_tramite' => $regla?->nombre,
            ]);
        }
        // Lo que vuelve de lo enviado (7.3.4): un rebote marca su envío; una respuesta detiene el plazo del documento.
        if ($this->entregas->esRebote($mensaje)) {
            $this->entregas->registrarRebote($mensaje, $correo);
        } elseif (! $nuevo) {
            $this->entregas->registrarRespuesta($mensaje, $expediente);
        }
        $this->auditoria->registrar('correo.ingresado', $correo, despues: [
            'expediente_id' => $expediente->id,
            'message_id' => $messageId,
            'sha256' => $hash,
            'adjuntos' => array_map(fn ($a) => ['nombre' => $a['adjunto']->nombre, 'sha256' => $a['sha256']], $adjuntos),
        ]);

        return $expediente;
    }

    /**
     * Hilo por In-Reply-To/References (correos recibidos o documentos enviados) y, si no, por el código REG-AAAA-NNNNN
     * del asunto (7.1, 7.2). Un rebote no se anexa al expediente: queda en su envío (EntregaService).
     */
    private function expedienteVinculado(MensajeLeido $mensaje): ?Expediente
    {
        if ($this->entregas->esRebote($mensaje)) {
            return null;
        }
        if ($mensaje->enRespuestaA !== []) {
            $id = Correo::whereIn('message_id', $mensaje->enRespuestaA)->orderBy('id')->value('expediente_id')
                ?? $this->entregas->envioReferido($mensaje)?->saliente?->expediente_id;
            if ($id) {
                return Expediente::find($id);
            }
        }

        return Expediente::porCodigoEn($mensaje->asunto);
    }

    private function estadoInicial(CarbonImmutable $fecha, ?ReglaNoTramite $regla): EstadoExpediente
    {
        $inicio = config('tramite.correo.inicio_operacion');

        return match (true) {
            $regla !== null => EstadoExpediente::NoTramite,
            $inicio && $fecha->lessThan(CarbonImmutable::parse($inicio)->startOfDay()) => EstadoExpediente::Historico,
            default => EstadoExpediente::PorRevisar,
        };
    }

    /**
     * Remitente real de un reenvío, tomado de la primera línea «De:»/«From:» del cuerpo.
     *
     * @return array{0: string, 1: ?string, 2: bool} email, nombre y si queda por confirmar
     */
    private function remitenteOriginal(MensajeLeido $mensaje): array
    {
        if (preg_match('/^\s*(?:De|From)\s*:\s*"?([^"<\n]*?)"?\s*<([^>\s]+@[^>\s]+)>/miu', $mensaje->cuerpo, $m)) {
            return [mb_strtolower($m[2]), trim($m[1]) ?: null, false];
        }
        if (preg_match('/^\s*(?:De|From)\s*:\s*([^\s<>]+@[^\s<>]+)/miu', $mensaje->cuerpo, $m)) {
            return [mb_strtolower($m[1]), null, false];
        }

        // Sin remitente reconocible: queda el que reenvió y el administrativo lo confirma.
        return [$mensaje->deEmail, $mensaje->deNombre, true];
    }

    /**
     * Analiza antes de procesar (11): un adjunto infectado se guarda aparte, sin extraer su texto.
     * Si el antivirus no responde, la excepción deja el correo sin marcar y se reintenta en la próxima pasada.
     *
     * @return array{adjunto: Adjunto, ruta: string, sha256: string, texto: ?string, amenaza: ?string}
     */
    private function guardarAdjunto(Adjunto $adjunto): array
    {
        $amenaza = $this->antivirus->amenaza($adjunto->contenido);
        ['ruta' => $ruta, 'sha256' => $sha256] = Documento::guardarArchivo($adjunto->contenido, $amenaza === null ? 'adjuntos' : 'cuarentena');

        return [
            'adjunto' => $adjunto,
            'ruta' => $ruta,
            'sha256' => $sha256,
            'texto' => $amenaza === null ? $this->texto->extraer(Storage::disk('originales')->path($ruta), $adjunto->mime) : null,
            'amenaza' => $amenaza,
        ];
    }

    /** Escribe una sola vez: el nombre es el hash, así que un archivo existente ya es idéntico. */
    private function guardarOriginal(string $ruta, string $contenido): string
    {
        $disco = Storage::disk('originales');
        if (! $disco->exists($ruta)) {
            $disco->put($ruta, $contenido);
        }

        return $ruta;
    }
}
