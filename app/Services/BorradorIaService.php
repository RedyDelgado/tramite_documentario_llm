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

    // Datos que la IA puede leer al registrar en papel, con la pista que necesita para cada uno.
    private const CAMPOS = [
        'tipo' => 'tipo (oficio, informe, carta, memorando, solicitud…)',
        'numero' => 'numero (tal como figura, con su tipo, p. ej. «INFORME N° 001-2026-UAC»)',
        'fecha' => 'fecha (AAAA-MM-DD)',
        'asunto' => 'asunto',
        'remitente' => 'remitente (quien lo firma o envía, sin el cargo)',
    ];

    public function __construct(private readonly AuditoriaService $auditoria) {}

    /** Sin URL de Ollama la función no existe en la interfaz (OLLAMA_URL en el .env). */
    public static function activo(): bool
    {
        return filled(config('tramite.llm.url'));
    }

    public function sugerir(Expediente $expediente, User $usuario): string
    {
        $texto = $this->generar($this->indicacion($expediente));

        // Se audita que se pidió y con qué modelo; el texto no, porque no es un documento del sistema.
        $this->auditoria->registrar('ia.borrador_sugerido', $expediente, despues: [
            'modelo' => config('tramite.llm.modelo'), 'caracteres' => mb_strlen($texto), 'usuario' => $usuario->id,
        ]);

        return $texto;
    }

    /**
     * Indicaciones para las áreas a las que se deriva: con borrador, lo hace más claro sin cambiar lo pedido; sin él,
     * las propone desde el documento. Quien deriva las revisa antes de enviar.
     *
     * @param  list<string>  $areas  nombres de las áreas que lo atenderán
     */
    public function indicaciones(Expediente $expediente, ?string $borrador, ?string $instruccion, array $areas, User $usuario): string
    {
        $expediente->loadMissing(['documentos', 'correos']);
        $documento = $expediente->documentos->pluck('texto_extraido')->filter()->implode("\n\n") ?: $expediente->correos->pluck('cuerpo_texto')->filter()->implode("\n\n");

        $destino = $areas !== [] ? implode(', ', $areas) : 'el área que corresponda';
        // Un modelo pequeño copia lo que tiene delante: para mejorar un borrador no se le da el documento, solo el asunto.
        $prompt = $borrador
            ? implode("\n", array_filter([
                'Mejora la redacción de estas indicaciones que escribió quien deriva un documento en la Filial Quillabamba de la Universidad Andina del Cusco.',
                'Hazlas claras, formales y directas, en 2 a 5 oraciones, sin saludo ni firma. No agregues tareas, plazos ni datos nuevos.',
                "Documento derivado: «{$expediente->asunto}». Se deriva a: {$destino}.",
                $instruccion ? "Instrucción elegida: {$instruccion}." : null,
                "Indicaciones a mejorar: «{$borrador}»",
                'Indicaciones mejoradas:',
            ]))
            : implode("\n", array_filter([
                'Eres asistente de la Filial Quillabamba de la Universidad Andina del Cusco.',
                // Menos texto que la respuesta sugerida: en CPU cada carácter alarga la espera.
                'Documento: '.Str::limit($documento ?: $expediente->asunto, 2000),
                '',
                "En 2 a 4 oraciones formales, sin saludo ni firma, escribe qué debe hacer {$destino} con este documento. No copies el documento ni inventes plazos o datos.",
                $instruccion ? "Instrucción elegida: {$instruccion}." : null,
                'Indicaciones:',
            ]));
        // Por si igual repite el encabezado de la respuesta o las comillas.
        $texto = trim(preg_replace('/^\s*Indicaciones( mejoradas)?\s*:\s*/u', '', $this->generar($prompt)), " \n«»\"");

        $this->auditoria->registrar('ia.indicaciones_sugeridas', $expediente, despues: [
            'modelo' => config('tramite.llm.modelo'), 'caracteres' => mb_strlen($texto), 'mejora' => $borrador !== null, 'usuario' => $usuario->id,
        ]);

        return Str::limit($texto, 2000, '');
    }

    /**
     * ¿El correo que llegó responde a lo que pidió la derivación? Solo propone: quien atiende lo confirma. Sin IA activa
     * devuelve null; si el modelo no responde, la excepción deja que el job reintente.
     *
     * @return array{responde: bool, motivo: string}|null
     */
    public function evaluarRespuesta(string $pedido, string $respuesta): ?array
    {
        if (! self::activo()) {
            return null;
        }

        $resultado = json_decode((string) Http::timeout(180)->post(rtrim(config('tramite.llm.url'), '/').'/api/generate', [
            'model' => config('tramite.llm.modelo'),
            'prompt' => implode("\n", [
                'Eres asistente de la Filial Quillabamba de la Universidad Andina del Cusco. Un área recibió un trámite con un pedido y contestó por correo.',
                'Lo pedido: '.Str::limit($pedido, 1500),
                'Lo que contestó: '.Str::limit($respuesta, 2500),
                '',
                'Responde solo JSON: {"responde": true o false, "motivo": "una oración en español"}. responde es true si la contestación atiende lo pedido (aunque sea en parte o adjunte lo solicitado) y false si trata de otra cosa o solo acusa recibo.',
            ]),
            'stream' => false,
            'format' => 'json',
            'options' => $this->opciones(['temperature' => 0]),
        ])->throw()->json('response'), true);

        return is_array($resultado) && is_bool($resultado['responde'] ?? null)
            ? ['responde' => $resultado['responde'], 'motivo' => Str::limit(trim((string) ($resultado['motivo'] ?? '')), 300, '')]
            : null;
    }

    private function generar(string $prompt): string
    {
        if (! self::activo()) {
            throw new ReglaDeNegocio('La IA no está activada en este servidor.');
        }

        try {
            // ponytail: espera síncrona (30-90 s en CPU); pasar a la cola si varios la piden a la vez.
            $respuesta = Http::timeout(180)->post(rtrim(config('tramite.llm.url'), '/').'/api/generate', [
                'model' => config('tramite.llm.modelo'),
                'prompt' => $prompt,
                'stream' => false,
                'options' => $this->opciones(['temperature' => 0.3]),
            ])->throw()->json();
        } catch (ConnectionException) {
            throw new ReglaDeNegocio('El modelo de IA no responde. Inténtalo en unos minutos o escríbelo sin ayuda.');
        }
        $texto = trim((string) ($respuesta['response'] ?? ''));
        if ($texto === '') {
            throw new ReglaDeNegocio('El modelo de IA no devolvió texto. Inténtalo de nuevo.');
        }

        return $texto;
    }

    /**
     * Lee el encabezado de un documento y devuelve los datos pedidos en `$claves` para completar lo que las reglas no
     * encontraron al registrar en papel. Sin IA activa o si no responde, devuelve [] y se sigue a mano.
     *
     * @param  list<string>  $claves  de self::CAMPOS; en CPU cada dato de más alarga la espera
     * @return array{tipo?: ?string, numero?: ?string, fecha?: ?string, asunto?: ?string, remitente?: ?string}
     */
    public function campos(string $texto, array $claves = ['tipo', 'numero', 'fecha', 'asunto', 'remitente']): array
    {
        $pedidos = array_intersect_key(self::CAMPOS, array_flip($claves));
        if (! self::activo() || $pedidos === []) {
            return [];
        }

        try {
            $respuesta = Http::timeout(180)->post(rtrim(config('tramite.llm.url'), '/').'/api/generate', [
                'model' => config('tramite.llm.modelo'),
                'prompt' => 'Extrae los datos del encabezado de este documento universitario peruano. Responde solo JSON con las claves '
                    .implode(', ', $pedidos).'. Usa null si un dato no figura; no inventes.'
                    ."\n\nDocumento:\n".Str::limit($texto, 4000),
                'stream' => false,
                'format' => 'json',
                'options' => $this->opciones(['temperature' => 0]),
            ])->throw()->json('response');
        } catch (Throwable $e) {
            report($e);

            return [];
        }

        $campos = json_decode((string) $respuesta, true);

        return is_array($campos) ? array_map(fn ($v) => is_string($v) && trim($v) !== '' ? trim($v) : null, array_intersect_key($campos, $pedidos)) : [];
    }

    /** Ollama usa un hilo por núcleo físico y en WSL o una VPS puede ver uno solo: LLM_HILOS fija cuántos usa. */
    private function opciones(array $opciones): array
    {
        return $opciones + array_filter(['num_thread' => config('tramite.llm.hilos')]);
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
