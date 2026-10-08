<?php

namespace App\Services;

use App\Enums\EstadoExpediente;
use App\Enums\OrigenExpediente;
use App\Models\Documento;
use App\Models\Emisor;
use App\Models\Expediente;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Registro de un documento en papel (7.3, 7.3.5): escaneo → texto → formulario prellenado → número. */
class RegistroFisicoService
{
    // El escaneo subido espera su confirmación un día; después hay que volver a subirlo.
    private const ESPERA = 86_400;

    public function __construct(
        private readonly TextoDocumentoService $texto,
        private readonly OcrService $ocr,
        private readonly ExtraccionService $extraccion,
        private readonly ExpedienteService $expedientes,
        private readonly AuditoriaService $auditoria,
        private readonly BorradorIaService $ia,
    ) {}

    /**
     * Guarda el escaneo y propone los campos. El OCR aquí es síncrono para prellenar; si el servicio de IA no responde, el formulario queda vacío y el OCR se encola al registrar.
     *
     * @return array{sha256: string, nombre: string, paginas: ?int, campos: array<string, mixed>, mismo_archivo: ?array{id: int, numero: ?string}}
     */
    public function prellenar(UploadedFile $archivo): array
    {
        $mime = $archivo->getMimeType();
        ['ruta' => $ruta, 'sha256' => $sha256] = Documento::guardarArchivo($archivo->getContent());
        $rutaAbsoluta = Storage::disk('originales')->path($ruta);

        $texto = $this->texto->extraer($rutaAbsoluta, $mime);
        $paginas = $this->texto->paginas($rutaAbsoluta, $mime);
        $porOcr = false;
        if ($texto === null && in_array($mime, OcrService::MIMES, true)) {
            try {
                $resultado = $this->ocr->reconocer($rutaAbsoluta, $mime);
                $texto = trim($resultado['texto']) ?: null;
                $paginas ??= $resultado['paginas'];
                $porOcr = $texto !== null;
            } catch (Throwable) {
                // Sin IA no hay prellenado, pero se puede registrar a mano (principio 2).
            }
        }

        Cache::put("registro-fisico:{$sha256}", [
            'ruta' => $ruta, 'mime' => $mime, 'nombre' => $archivo->getClientOriginalName(), 'paginas' => $paginas,
            'texto' => $texto, 'por_ocr' => $porOcr,
        ], self::ESPERA);

        $campos = $texto ? $this->completarConIa($this->extraccion->extraer($texto), $texto) : [];
        $igual = Documento::where('sha256', $sha256)->with('expediente')->first()?->expediente;

        return [
            'sha256' => $sha256,
            'nombre' => $archivo->getClientOriginalName(),
            'paginas' => $paginas,
            'campos' => $campos + ['folios' => $paginas],
            'mismo_archivo' => $igual ? ['id' => $igual->id, 'numero' => $igual->numero_registro] : null,
        ];
    }

    /**
     * Lo que las reglas no encontraron lo propone la IA local, si está activa (fase 6); la persona confirma igual.
     *
     * @param  array<string, mixed>  $campos
     * @return array<string, mixed>
     */
    private function completarConIa(array $campos, string $texto): array
    {
        $faltan = array_filter(['tipo_documento_id', 'numero_documento_original', 'fecha_documento', 'asunto'], fn ($c) => empty($campos[$c]));
        if (! BorradorIaService::activo() || ($faltan === [] && ($campos['emisor_id'] || $campos['emisor_sugerido']))) {
            return $campos;
        }
        $ia = $this->ia->campos($texto);
        $fecha = isset($ia['fecha']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $ia['fecha']) ? $ia['fecha'] : null;

        return [
            'tipo_documento_id' => $campos['tipo_documento_id'] ?? (isset($ia['tipo']) ? $this->extraccion->tipoDocumento($ia['tipo']) : null),
            'numero_documento_original' => $campos['numero_documento_original'] ?? $ia['numero'] ?? null,
            'fecha_documento' => $campos['fecha_documento'] ?? $fecha,
            'asunto' => $campos['asunto'] ?? $ia['asunto'] ?? null,
            'emisor_id' => $campos['emisor_id'] ?? (isset($ia['remitente']) ? $this->extraccion->emisor($ia['remitente']) : null),
            'emisor_sugerido' => $campos['emisor_sugerido'] ?? $ia['remitente'] ?? null,
        ];
    }

    /**
     * Crea el expediente con número de registro, dentro de la transacción que comprueba duplicados (7.3.5).
     *
     * @param  array<string, mixed>  $datos  validados por RegistroFisicoRequest
     *
     * @throws ValidationException si es un duplicado
     */
    public function registrar(array $datos): Expediente
    {
        $archivo = Cache::get("registro-fisico:{$datos['sha256']}");
        if (! $archivo) {
            throw ValidationException::withMessages(['archivo' => 'El escaneo ya no está disponible; vuelve a subirlo.']);
        }
        $numero = isset($datos['numero_documento']) ? ExtraccionService::normalizarNumero($datos['numero_documento']) : null;

        try {
            $expediente = DB::transaction(function () use ($datos, $archivo, $numero) {
                $this->comprobarDuplicados($datos, $numero);

                $emisor = isset($datos['emisor_id']) ? Emisor::find($datos['emisor_id']) : null;
                $enCurso = (bool) ($datos['en_curso'] ?? false);
                $expediente = Expediente::create([
                    'origen' => OrigenExpediente::Fisico,
                    'estado' => EstadoExpediente::PorRevisar,
                    'asunto' => $datos['asunto'],
                    'remitente_nombre' => $emisor?->nombre,
                    // Un trámite en curso entra con su fecha real: de ella salen el plazo y el semáforo (8).
                    'fecha_ingreso' => $enCurso ? CarbonImmutable::parse($datos['fecha_ingreso'])->startOfDay() : now(),
                    'tipo_documento_id' => $datos['tipo_documento_id'] ?? null,
                    'numero_documento' => $numero,
                    'numero_documento_original' => $datos['numero_documento'] ?? null,
                    'fecha_documento' => $datos['fecha_documento'] ?? null,
                    'folios' => $datos['folios'],
                    'motivo_folios' => $datos['motivo_folios'] ?? null,
                    'requiere_respuesta' => $datos['requiere_respuesta'],
                    'ubicacion_fisica_id' => $datos['ubicacion_fisica_id'] ?? null,
                    // Quien recibe el papel es su primer custodio (7.3.1).
                    'custodio_id' => auth()->id(),
                ]);
                Documento::create([
                    'expediente_id' => $expediente->id,
                    'nombre_original' => $archivo['nombre'],
                    'ruta' => $archivo['ruta'],
                    'mime' => $archivo['mime'],
                    'tamano' => Storage::disk('originales')->size($archivo['ruta']),
                    'paginas' => $archivo['paginas'],
                    'sha256' => $datos['sha256'],
                    'texto_extraido' => $archivo['texto'],
                    'es_adjunto' => false,
                ])->forceFill(['texto_por_ocr' => $archivo['por_ocr']])->save();
                $this->auditoria->registrar('expediente.creado', $expediente, despues: [
                    'origen' => 'fisico', 'sha256' => $datos['sha256'], 'folios' => $datos['folios'],
                    ...($enCurso ? ['en_curso' => ['numero_papel' => (int) $datos['numero_papel'], 'fecha_ingreso' => $datos['fecha_ingreso']]] : []),
                ]);

                // Mismo número y auditoría que un correo confirmado (6.2); el trámite en curso conserva el suyo.
                return $this->expedientes->confirmar(
                    $expediente,
                    ['emisor_id' => $datos['emisor_id'] ?? null, 'tipo_documento_id' => $datos['tipo_documento_id'] ?? null],
                    $enCurso ? (int) $datos['numero_papel'] : null,
                );
            });
        } catch (UniqueConstraintViolationException) {
            // Dos registros simultáneos del mismo documento: el índice único es la última barrera.
            $this->comprobarDuplicados($datos, $numero);
            throw ValidationException::withMessages(['numero_documento' => 'Este documento se acaba de registrar.']);
        }

        Cache::forget("registro-fisico:{$datos['sha256']}");

        return $expediente;
    }

    /** Último número del registro en papel del año: los trámites en curso usan del 1 hasta aquí; el sistema sigue desde el siguiente. */
    public static function ultimoNumeroEnPapel(): int
    {
        return app(SecuenciaService::class)->inicio('registro', now()->year) - 1;
    }

    /** Clave de negocio: bloquea. Mismo archivo o mismo emisor, asunto y fecha: avisa hasta que se confirme. */
    private function comprobarDuplicados(array $datos, ?string $numero): void
    {
        $vigentes = fn () => Expediente::where('estado', '!=', EstadoExpediente::Anulado);

        if ($numero && isset($datos['emisor_id'], $datos['tipo_documento_id'], $datos['fecha_documento'])) {
            $existente = $vigentes()->where('emisor_id', $datos['emisor_id'])->where('tipo_documento_id', $datos['tipo_documento_id'])
                ->where('numero_documento', $numero)
                ->whereRaw('EXTRACT(YEAR FROM fecha_documento) = ?', [(int) substr($datos['fecha_documento'], 0, 4)])
                ->first();
            if ($existente) {
                throw ValidationException::withMessages([
                    'numero_documento' => "Ya registrado como {$existente->numero_registro} ({$existente->codigo}).",
                    'duplicado_id' => (string) $existente->id,
                ]);
            }
        }

        if ($datos['confirmar_duplicado'] ?? false) {
            return;
        }
        if (Documento::where('sha256', $datos['sha256'])->exists()) {
            throw ValidationException::withMessages(['duplicado' => 'Este mismo archivo ya está en otro expediente. Si es otro documento, confirma para registrarlo.']);
        }
        if (isset($datos['emisor_id'], $datos['fecha_documento'])
            && $vigentes()->where('emisor_id', $datos['emisor_id'])->whereDate('fecha_documento', $datos['fecha_documento'])
                ->whereRaw('lower(asunto) = lower(?)', [$datos['asunto']])->exists()) {
            throw ValidationException::withMessages(['duplicado' => 'Ya hay un documento del mismo emisor, asunto y fecha. Si es otro, confirma para registrarlo.']);
        }
    }
}
