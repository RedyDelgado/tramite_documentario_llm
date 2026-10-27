<?php

namespace App\Services;

use App\Enums\EstadoExpediente;
use App\Exceptions\ReglaDeNegocio;
use App\Models\Expediente;
use App\Models\Movimiento;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
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
        $expediente->loadMissing(['documentos', 'correos', 'tipoDocumento:id,nombre', 'emisor:id,nombre', 'institucion:id,nombre']);
        $documento = $expediente->documentos->pluck('texto_extraido')->filter()->implode('

') ?: $expediente->correos->pluck('cuerpo_texto')->filter()->implode('

');

        $destino = $areas !== [] ? implode(', ', $areas) : 'el área que corresponda';
        $remitente = collect([$expediente->emisor?->nombre ?? $expediente->remitente_nombre, $expediente->institucion?->nombre])->filter()->unique()->implode(', ');
        // Lo que un modelo pequeño necesita para no escribir vaguedades: qué es, de quién, a quién, qué se espera y un ejemplo.
        $contexto = array_filter([
            'Documento derivado: '.($expediente->tipoDocumento?->nombre ?? 'documento')." «{$expediente->asunto}»".($remitente ? ", enviado por {$remitente}" : '').'.',
            "Se deriva a: {$destino}.",
            $instruccion ? "Instrucción elegida: {$instruccion}." : null,
            $expediente->requiere_respuesta
                ? 'Requiere respuesta: el área debe contestar al correo del aviso con lo que hizo y adjuntar lo que corresponda.'
                : 'No requiere respuesta escrita: es para conocimiento o acción del área.',
            'Reglas: empieza con un verbo en infinitivo (Revisar, Atender, Informar, Remitir…); di qué hacer en concreto con este documento y, si requiere respuesta, cómo responder. '
                .'2 a 4 oraciones, formales, sin saludo ni firma. No inventes plazos, nombres ni datos que no estén aquí.',
            'Ejemplo de buenas indicaciones: «Revisar la solicitud de ampliación de matrícula del estudiante y verificar si cumple los requisitos del reglamento. '
                .'Responder a este correo con su opinión y adjuntar el informe correspondiente.»',
        ]);
        // Un modelo pequeño copia lo que tiene delante: para mejorar un borrador no se le da el documento, solo el contexto.
        $prompt = $borrador
            ? implode('
', [
                'Mejora la redacción de estas indicaciones que escribió quien deriva un documento en la Filial Quillabamba de la Universidad Andina del Cusco. No agregues tareas nuevas.',
                ...$contexto,
                "Indicaciones a mejorar: «{$borrador}»",
                'Indicaciones mejoradas:',
            ])
            : implode('
', [
                'Eres asistente de la Filial Quillabamba de la Universidad Andina del Cusco. Escribe las indicaciones para quien atenderá este documento.',
                // Menos texto que la respuesta sugerida: en CPU cada carácter alarga la espera.
                'Texto del documento: '.Str::limit($documento ?: $expediente->asunto, 2000),
                ...$contexto,
                'Indicaciones:',
            ]);
        // Por si igual repite el encabezado de la respuesta o las comillas.
        $texto = trim(preg_replace('/^\s*Indicaciones( mejoradas)?\s*:\s*/u', '', $this->generar($prompt, 220)), " \n«»\"");

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

    /**
     * Chat de consulta: busca los expedientes que el usuario ve y responde solo con ellos, citando el código. Le da
     * además las cifras por estado para las preguntas de cantidad.
     *
     * @param  list<array{rol: string, texto: string}>  $historial  turnos previos, para preguntas de seguimiento
     * @return array{respuesta: string, fuentes: list<array{id: int, numero: ?string, asunto: string}>}
     */
    public function consultar(string $pregunta, array $historial, User $usuario): array
    {
        $expedientes = $this->relacionados($pregunta.' '.collect($historial)->where('rol', 'usuario')->pluck('texto')->implode(' '), $usuario);
        // Cifras de todo lo visible: con solo 5 fichas el modelo no puede contestar «¿cuántos…?».
        $porEstado = Expediente::visiblesPara($usuario)->toBase()->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');
        $resumen = collect(EstadoExpediente::cases())->map(fn (EstadoExpediente $e) => $e->etiqueta().': '.($porEstado[$e->value] ?? 0))->implode(', ')
            .'. Derivados con alguna área que aún no atiende: '.Expediente::visiblesPara($usuario)->whereHas('atenciones', fn ($q) => $q->where('estado', '!=', 'atendido'))->count()
            .'. Con respuesta de un área sin revisar: '.Expediente::visiblesPara($usuario)->conRespuestaNueva()->count().'.';

        $fichas = $expedientes->map(fn (Expediente $e) => implode("\n", array_filter([
            "[{$e->codigo}] {$e->asunto}",
            'Remitente: '.($e->remitente_nombre ?? $e->remitente_email ?? '—').($e->institucion ? " ({$e->institucion->nombre})" : ''),
            "Ingreso: {$e->fecha_ingreso->format('d/m/Y')}. Estado: {$e->estado->etiqueta()}.".($e->fecha_limite ? " Fecha límite: {$e->fecha_limite->format('d/m/Y')}." : ''),
            $e->atenciones->isNotEmpty() ? 'Áreas: '.$e->atenciones->map(fn ($a) => $a->area->nombre.' ('.($a->estado === 'atendido' ? 'atendido'.($a->nota_atencion ? ': '.Str::limit($a->nota_atencion, 200) : '') : 'pendiente').')')->implode('; ') : null,
            ($r = $e->correos->where('respuesta_area', true)->last()) ? "Respuesta de {$r->de_email} el {$r->created_at->format('d/m/Y')}: ".Str::limit((string) $r->cuerpo_texto, 500) : null,
            'Texto: '.Str::limit($e->textoCompleto(1200), 700),
        ])))->implode("\n\n");

        $texto = $this->generar(implode("\n", [
            'Eres el asistente de trámite documentario de la Filial Quillabamba de la Universidad Andina del Cusco. Responde en español, en 2 a 4 oraciones completas,',
            'usando SOLO los trámites de abajo: qué es, en qué estado está, qué áreas lo atienden y qué respondieron. Menciona el código entre corchetes de cada trámite del que hablas.',
            'Para preguntas de cantidades usa las cifras del resumen general y da el número directo, sin nombrar el resumen. Si la respuesta no está aquí, dilo; no inventes. Ejemplo: «El trámite [REG-2026-00012] es una solicitud de constancia de la Escuela de Derecho; está derivado a Secretaría Académica, que aún no responde.»',
            '',
            "Resumen general (cantidad de trámites): {$resumen}",
            '',
            'Trámites:',
            $fichas ?: '(ninguno relacionado con la pregunta)',
            '',
            ...collect($historial)->take(-4)->map(fn ($t) => ($t['rol'] === 'usuario' ? 'Usuario: ' : 'Asistente: ').Str::limit($t['texto'], 500)),
            "Usuario: {$pregunta}",
            'Asistente:',
        ]), 400);

        // Se audita la pregunta y qué expedientes leyó la IA; no la respuesta, que no es un documento del sistema.
        $this->auditoria->registrar('ia.consulta', $usuario, despues: [
            'pregunta' => Str::limit($pregunta, 500), 'expedientes' => $expedientes->pluck('id')->all(), 'modelo' => config('tramite.llm.modelo'),
        ]);

        // Enlaza solo los que la IA citó: en una respuesta de cantidades, lo que leyó de paso no viene al caso.
        $citados = $expedientes->filter(fn (Expediente $e) => $e->codigo && str_contains($texto, $e->codigo));

        return [
            'respuesta' => $texto,
            'fuentes' => $citados->map(fn (Expediente $e) => ['id' => $e->id, 'numero' => $e->numero_registro, 'asunto' => $e->asunto])->values()->all(),
        ];
    }

    /** Hasta 5 expedientes visibles: por código citado, por el índice de búsqueda y, si este no trae nada, por el asunto. */
    private function relacionados(string $texto, User $usuario): Collection
    {
        $con = ['institucion:id,nombre', 'atenciones.area:id,nombre', 'correos', 'documentos'];
        if (($porCodigo = Expediente::porCodigoEn($texto)) && $usuario->can('view', $porCodigo)) {
            return collect([$porCodigo->load($con)]);
        }
        // Sin palabras vacías la búsqueda no exige «qué», «del» o «trámite» en el documento.
        $palabras = collect(preg_split('/[^\p{L}\p{N}-]+/u', mb_strtolower($texto)))
            ->filter(fn ($p) => mb_strlen($p) > 3 && ! in_array($p, ['para', 'sobre', 'cual', 'cuál', 'cuales', 'cuáles', 'como', 'cómo', 'donde', 'dónde', 'cuando', 'cuándo', 'quien', 'quién', 'tiene', 'tienen', 'hay', 'este', 'esta', 'estos', 'esas', 'esos', 'trámite', 'tramite', 'trámites', 'tramites', 'expediente', 'expedientes', 'llegó', 'llego', 'dime', 'muestra', 'respondió', 'respondio', 'pasado', 'pasados'], true))
            ->unique()->take(8)->values();
        if ($palabras->isEmpty()) {
            return collect();
        }

        $busqueda = Expediente::search($palabras->implode(' '))->query(fn ($q) => $q->visiblesPara($usuario)->with($con));
        if (($tokens = Expediente::tokensVisiblesPara($usuario)) !== null) {
            $busqueda->whereIn('visible_para', $tokens);
        }
        $encontrados = $busqueda->take(5)->get();

        return $encontrados->isNotEmpty() ? $encontrados : Expediente::visiblesPara($usuario)->with($con)
            ->where(fn ($q) => $palabras->each(fn ($p) => $q->orWhere('asunto', 'ilike', "%{$p}%")->orWhere('remitente_nombre', 'ilike', "%{$p}%")))
            ->latest('fecha_ingreso')->limit(5)->get();
    }

    /** @param  ?int  $tope  tokens como máximo: unas indicaciones no pasan de un párrafo y cortar antes ahorra segundos en CPU */
    private function generar(string $prompt, ?int $tope = null): string
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
                'options' => $this->opciones(array_filter(['temperature' => 0.3, 'num_predict' => $tope])),
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
