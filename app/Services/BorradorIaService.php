<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocio;
use App\Models\Expediente;
use App\Models\Movimiento;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Fase 6: sugiere el texto de una respuesta con un modelo de lenguaje local (Ollama), sin enviar nada a terceros.
 * Es solo una sugerencia: quien atiende la revisa, la pasa a su Word y el documento sigue el flujo con aprobación (7.3.4).
 */
class BorradorIaService
{
    // Lo que el modelo lee del expediente: suficiente para el contexto, sin pasar de su ventana.
    private const MAX_TEXTO = 6000;

    public function __construct(private readonly AuditoriaService $auditoria) {}

    /** Sin URL de Ollama la función no existe en la interfaz (OLLAMA_URL en el .env). */
    public static function activo(): bool
    {
        return filled(config('tramite.llm.url'));
    }

    public function sugerir(Expediente $expediente, User $usuario): string
    {
        if (! self::activo()) {
            throw new ReglaDeNegocio('La sugerencia con IA no está activada en este servidor.');
        }

        try {
            // ponytail: espera síncrona (30-90 s en CPU); pasar a la cola si varios la piden a la vez.
            $respuesta = Http::timeout(180)->post(rtrim(config('tramite.llm.url'), '/').'/api/generate', [
                'model' => config('tramite.llm.modelo'),
                'prompt' => $this->indicacion($expediente),
                'stream' => false,
                'options' => ['temperature' => 0.3],
            ])->throw()->json();
        } catch (ConnectionException) {
            throw new ReglaDeNegocio('El modelo de IA no responde. Inténtalo en unos minutos o redacta sin sugerencia.');
        }
        $texto = trim((string) ($respuesta['response'] ?? ''));
        if ($texto === '') {
            throw new ReglaDeNegocio('El modelo de IA no devolvió texto. Inténtalo de nuevo.');
        }

        // Se audita que se pidió y con qué modelo; el texto no, porque no es un documento del sistema.
        $this->auditoria->registrar('ia.borrador_sugerido', $expediente, despues: [
            'modelo' => config('tramite.llm.modelo'), 'caracteres' => mb_strlen($texto), 'usuario' => $usuario->id,
        ]);

        return $texto;
    }

    /**
     * Lee el encabezado de un documento y devuelve sus datos (tipo, número, fecha, asunto, remitente) para completar lo
     * que las reglas no encontraron al registrar en papel. Sin IA activa o si no responde, devuelve [] y se sigue a mano.
     *
     * @return array{tipo?: ?string, numero?: ?string, fecha?: ?string, asunto?: ?string, remitente?: ?string}
     */
    public function campos(string $texto): array
    {
        if (! self::activo()) {
            return [];
        }

        try {
            $respuesta = Http::timeout(120)->post(rtrim(config('tramite.llm.url'), '/').'/api/generate', [
                'model' => config('tramite.llm.modelo'),
                'prompt' => 'Extrae los datos del encabezado de este documento universitario peruano. Responde solo JSON con las claves '
                    .'tipo (oficio, informe, carta, memorando, solicitud…), numero (tal como figura, con su tipo, p. ej. «INFORME N° 001-2026-UAC»), '
                    .'fecha (AAAA-MM-DD), asunto y remitente (quien lo firma o envía, sin el cargo). Usa null si un dato no figura; no inventes.'
                    ."\n\nDocumento:\n".Str::limit($texto, 4000),
                'stream' => false,
                'format' => 'json',
                'options' => ['temperature' => 0],
            ])->throw()->json('response');
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $campos = json_decode((string) $respuesta, true);

        return is_array($campos) ? array_map(fn ($v) => is_string($v) && trim($v) !== '' ? trim($v) : null, array_intersect_key($campos, array_flip(['tipo', 'numero', 'fecha', 'asunto', 'remitente']))) : [];
    }

    private function indicacion(Expediente $e): string
    {
        $e->loadMissing(['emisor', 'documentos', 'correos']);
        $instruccion = Movimiento::where('expediente_id', $e->id)->where('tipo', 'derivacion')->latest('id')->value('instruccion');
        $texto = $e->documentos->pluck('texto_extraido')->filter()->implode("\n\n") ?: $e->correos->pluck('cuerpo_texto')->filter()->implode("\n\n");

        return implode("\n", array_filter([
            'Eres asistente de la Filial Quillabamba de la Universidad Andina del Cusco. Redacta en español formal y sobrio el cuerpo de un oficio de respuesta al documento recibido.',
            'No inventes datos, cifras, nombres ni fechas que no estén en el documento; donde falte un dato escribe [COMPLETAR]. No incluyas número de oficio ni firma.',
            '',
            'Documento recibido: '.($e->numero_documento_original ?? $e->numero_documento ?? 'sin número'),
            'Remitente: '.($e->emisor?->nombre ?? $e->remitente_nombre ?? $e->remitente_email ?? 'no indicado'),
            'Asunto: '.$e->asunto,
            $instruccion ? "Instrucción de la dirección: {$instruccion}" : null,
            '',
            'Texto del documento:',
            Str::limit($texto ?: '(sin texto)', self::MAX_TEXTO),
        ], fn ($linea) => $linea !== null));
    }
}
